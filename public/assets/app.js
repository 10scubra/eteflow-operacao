const body = document.body;
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
let serverBase = Date.parse(body.dataset.serverTime || new Date().toISOString());
let synchronizedAt = Date.now();

function syncClock(value) {
    const parsed = Date.parse(value);
    if (!Number.isNaN(parsed)) {
        serverBase = parsed;
        synchronizedAt = Date.now();
    }
}

function serverNow() {
    return new Date(serverBase + (Date.now() - synchronizedAt));
}

function formatClock(date) {
    return new Intl.DateTimeFormat('pt-BR', {
        timeZone: 'America/Sao_Paulo',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    }).format(date);
}

function formatDate(date) {
    return new Intl.DateTimeFormat('pt-BR', {
        timeZone: 'America/Sao_Paulo',
        weekday: 'short',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date);
}

function updateClock() {
    const now = serverNow();
    document.querySelectorAll('#live-clock').forEach((element) => {
        element.textContent = formatClock(now);
    });
    document.querySelectorAll('#live-date').forEach((element) => {
        element.textContent = formatDate(now);
    });
}
updateClock();
setInterval(updateClock, 1000);

function escapeHtml(value = '') {
    return String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[character]);
}

function toast(message, error = false) {
    const element = document.querySelector('#toast');
    if (!element) return;
    element.textContent = message;
    element.className = 'toast show' + (error ? ' error' : '');
    clearTimeout(window.eteToastTimer);
    window.eteToastTimer = setTimeout(() => {
        element.className = 'toast';
    }, 3800);
}

function relativeTime(iso) {
    if (!iso) return 'sem registro';
    const difference = Date.parse(iso) - serverNow().getTime();
    const absolute = Math.abs(difference);
    const formatter = new Intl.RelativeTimeFormat('pt-BR', { numeric: 'auto' });
    if (absolute < 60000) return formatter.format(Math.round(difference / 1000), 'second');
    if (absolute < 3600000) return formatter.format(Math.round(difference / 60000), 'minute');
    return formatter.format(Math.round(difference / 3600000), 'hour');
}

async function api(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            ...(options.method && options.method !== 'GET' ? {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
            } : {}),
            ...options.headers,
        },
        ...options,
    });
    const data = await response.json().catch(() => ({}));
    if (data.server_time) syncClock(data.server_time);
    return { response, data };
}

const statusLabels = {
    pending: 'Pendente',
    in_progress: 'Em andamento',
    completed: 'Concluído',
    reopened: 'Reaberto',
    not_applicable: 'Não aplicável',
};

if (body.dataset.page === 'readings') {
    const panel = document.querySelector('#reading-panel');
    const tabs = [...document.querySelectorAll('.section-tab')];
    let activeSectionId = null;

    async function loadSection(id, force = false) {
        activeSectionId = Number(id);
        tabs.forEach((tab) => tab.classList.toggle('active', Number(tab.dataset.sectionId) === activeSectionId));
        panel.innerHTML = '<div class="loading-card">Carregando bloco…</div>';

        const opened = await api('/api/reading-sections/' + activeSectionId + '/open', {
            method: 'POST',
            body: JSON.stringify({ force }),
        });

        if (opened.response.status === 409) {
            panel.innerHTML = `<div class="presence-warning"><span class="status-badge in_progress">EM EDIÇÃO</span><h2>${escapeHtml(opened.data.editor || 'Outro operador')} está preenchendo este bloco</h2><p>Aberto ${relativeTime(opened.data.editing_started_at)}. Você pode escolher outro bloco ou abrir mesmo assim. Nenhum salvamento será sobrescrito silenciosamente.</p><div class="panel-actions"><button type="button" class="button secondary" id="cancel-open">Escolher outro bloco</button><button type="button" class="button primary" id="force-open">Abrir mesmo assim</button></div></div>`;
            document.querySelector('#force-open')?.addEventListener('click', () => loadSection(activeSectionId, true));
            document.querySelector('#cancel-open')?.addEventListener('click', () => {
                panel.innerHTML = '<div class="empty-state">Escolha outro bloco da rodada.</div>';
            });
            return;
        }

        if (!opened.response.ok) {
            panel.innerHTML = '<div class="form-alert">Não foi possível registrar a abertura deste bloco.</div>';
            return;
        }

        const { response, data } = await api('/api/reading-sections/' + activeSectionId);
        if (!response.ok) {
            panel.innerHTML = '<div class="form-alert">Não foi possível carregar este bloco.</div>';
            return;
        }

        const section = data.section;
        const values = new Map(section.values.map((value) => [value.field_key, value]));
        const previousValues = new Map((data.previous_values || []).map((value) => [value.field_key, value]));
        const fields = data.rules.map((rule) => {
            const saved = values.get(rule.field_key);
            const isText = rule.minimum_value === null && rule.maximum_value === null && rule.unit === null;
            const range = rule.minimum_value !== null || rule.maximum_value !== null
                ? [rule.minimum_value ?? '—', rule.maximum_value ?? '—'].join(' a ') + (rule.unit ? ' ' + rule.unit : '')
                : 'Texto livre';
            const control = isText
                ? `<textarea data-field="${escapeHtml(rule.field_key)}" data-type="text" required placeholder="Registre a observação">${escapeHtml(saved?.value_text ?? '')}</textarea>`
                : `<div class="input-unit"><input inputmode="decimal" type="number" step="any" data-field="${escapeHtml(rule.field_key)}" data-type="number" required value="${escapeHtml(saved?.value_numeric ?? '')}" placeholder="0,00">${rule.unit ? `<span>${escapeHtml(rule.unit)}</span>` : ''}</div>`;
            return `<label class="field-card field"><span class="field-meta"><b>${escapeHtml(rule.label)}</b><small>${escapeHtml(range)}</small></span>${control}</label>`;
        }).join('');

        panel.innerHTML = `
            <form id="reading-form">
                <div class="panel-title">
                    <div><h2>${escapeHtml(section.label)}</h2><p>${section.last_edited_by ? 'Última edição por ' + escapeHtml(section.last_edited_by.name) + ' ' + relativeTime(section.updated_at) : 'Aguardando primeira leitura'}</p></div>
                    <span class="status-badge ${escapeHtml(section.status)}">${escapeHtml(statusLabels[section.status] || section.status)}</span>
                </div>
                <div id="conflict-area"></div>
                <div class="fields-grid">${fields || '<div class="empty-state">Nenhum parâmetro configurado para este bloco.</div>'}</div>
                <div class="panel-actions">
                    ${previousValues.size ? '<button class="button ghost" type="button" id="copy-previous">Usar última leitura (' + escapeHtml(data.previous_round_time || '') + ')</button>' : ''}
                    <button class="button secondary" type="submit" value="in_progress">Salvar rascunho</button>
                    <button class="button primary" type="submit" value="completed">Concluir bloco</button>
                </div>
            </form>
        `;

        document.querySelector('#copy-previous')?.addEventListener('click', () => {
            if (!window.confirm('Copiar os valores da rodada ' + (data.previous_round_time || 'anterior') + '? Você poderá revisar antes de salvar.')) return;

            document.querySelectorAll('#reading-form [data-field]').forEach((input) => {
                const previous = previousValues.get(input.dataset.field);
                if (!previous) return;
                input.value = input.dataset.type === 'text'
                    ? (previous.value_text ?? '')
                    : (previous.value_numeric ?? '');
            });
            toast('Valores anteriores copiados. Revise antes de salvar.');
        });

        document.querySelector('#reading-form')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitter = event.submitter;
            const valuesPayload = [...event.currentTarget.querySelectorAll('[data-field]')].map((input) => {
                const entry = { field_key: input.dataset.field };
                if (input.dataset.type === 'text') entry.value_text = input.value;
                else entry.value_numeric = input.value === '' ? null : Number(input.value);
                return entry;
            });
            const buttons = [...event.currentTarget.querySelectorAll('button')];
            buttons.forEach((button) => button.disabled = true);

            const result = await api('/api/reading-sections/' + activeSectionId, {
                method: 'PUT',
                body: JSON.stringify({
                    lock_version: section.lock_version,
                    status: submitter?.value || 'in_progress',
                    values: valuesPayload,
                }),
            });

            buttons.forEach((button) => button.disabled = false);

            if (result.response.status === 409) {
                const area = document.querySelector('#conflict-area');
                area.innerHTML = `<div class="inline-alert"><b>Outro operador salvou este bloco.</b><br>${escapeHtml(result.data.last_editor || 'Outro usuário')} alterou ${relativeTime(result.data.updated_at)}. <button type="button" class="button secondary" id="reload-conflict">Atualizar bloco</button></div>`;
                document.querySelector('#reload-conflict').addEventListener('click', () => loadSection(activeSectionId));
                toast('Conflito detectado. Atualize o bloco antes de salvar.', true);
                return;
            }

            if (!result.response.ok) {
                const firstError = Object.values(result.data.errors || {}).flat()[0];
                toast(firstError || result.data.message || 'Não foi possível salvar.', true);
                return;
            }

            const tab = tabs.find((item) => Number(item.dataset.sectionId) === activeSectionId);
            if (tab) tab.dataset.status = result.data.section.status;
            updateProgress();

            const nextPending = result.data.section.status === 'completed'
                ? tabs.find((item) => Number(item.dataset.sectionId) !== activeSectionId && item.dataset.status !== 'completed')
                : null;

            if (nextPending) {
                toast(result.data.warnings?.length
                    ? 'Bloco concluído com alerta. Próximo: ' + nextPending.querySelector('span').textContent
                    : 'Bloco concluído. Abrindo o próximo pendente.');
                loadSection(nextPending.dataset.sectionId);
            } else {
                toast(result.data.warnings?.length ? 'Bloco salvo com valor fora da faixa.' : 'Bloco salvo com sucesso.', false);
                loadSection(activeSectionId);
            }
        });
    }

    function updateProgress() {
        const completed = tabs.filter((tab) => tab.dataset.status === 'completed').length;
        document.querySelector('#round-progress').textContent = completed + '/' + tabs.length;
        document.querySelector('#progress-bar').style.width = (completed / Math.max(1, tabs.length) * 100) + '%';
    }

    tabs.forEach((tab) => tab.addEventListener('click', () => loadSection(tab.dataset.sectionId)));
    if (tabs[0]) loadSection(tabs[0].dataset.sectionId);
}

if (body.dataset.page === 'master') {
    const loading = document.querySelector('#master-loading');
    const content = document.querySelector('#master-content');

    function displayRound(snapshot) {
        return snapshot.rounds.find((round) => round.id === snapshot.display_round_id) || snapshot.rounds.at(-1);
    }

    function renderSnapshot(snapshot) {
        syncClock(snapshot.server_time);
        loading.hidden = true;
        content.hidden = false;

        document.querySelector('#shift-title').textContent = 'Turno ' + snapshot.shift.name.toLowerCase();
        document.querySelector('#shift-team').textContent = snapshot.shift.members.map((member) => member.name).join(' + ') || 'Nenhum operador vinculado';
        document.querySelector('#shift-window').textContent = snapshot.shift.starts_at + ' → ' + snapshot.shift.ends_at;
        document.querySelector('#shift-state').textContent = snapshot.shift.status === 'active' ? 'Turno ativo' : snapshot.shift.status === 'planned' ? 'Turno programado' : 'Turno encerrado';

        const current = displayRound(snapshot);
        const isBeforeFirst = snapshot.current_round_id === null && snapshot.next_round_id !== null;
        document.querySelector('#round-kicker').textContent = isBeforeFirst ? 'PRÓXIMA RODADA' : snapshot.next_round_id === null ? 'ÚLTIMA RODADA' : 'RODADA ATUAL';
        document.querySelector('#current-round-time').textContent = current?.time || '--:--';
        document.querySelector('#current-round-relative').textContent = current ? relativeTime(current.scheduled_at) : 'sem rodada';
        document.querySelector('#master-progress-bar').style.width = (current?.progress || 0) + '%';
        document.querySelector('#master-progress-copy').textContent = current ? current.completed + ' de ' + current.total + ' blocos concluídos' : 'Sem dados';

        const completedSections = snapshot.rounds.reduce((sum, round) => sum + round.completed, 0);
        const totalSections = snapshot.rounds.reduce((sum, round) => sum + round.total, 0);
        document.querySelector('#kpi-completed').textContent = completedSections + '/' + totalSections;
        document.querySelector('#kpi-completed-copy').textContent = snapshot.rounds.filter((round) => round.status === 'completed').length + ' rodadas completas';
        document.querySelector('#kpi-overdue').textContent = snapshot.rounds.filter((round) => round.is_overdue).length;
        document.querySelector('#kpi-alerts').textContent = snapshot.alerts.length;
        document.querySelector('#kpi-team').textContent = snapshot.shift.members.length;
        document.querySelector('#kpi-actions').textContent = snapshot.operations.pending_actions;
        document.querySelector('#kpi-actions-copy').textContent = snapshot.operations.overdue_actions + ' atrasada(s)';
        document.querySelector('#kpi-occurrences').textContent = snapshot.operations.open_occurrences;
        document.querySelector('#kpi-aspersions').textContent = snapshot.operations.active_aspersions;
        document.querySelector('#kpi-equipment').textContent = snapshot.operations.stopped_equipment;
        document.querySelector('#kpi-equipment-copy').textContent = snapshot.operations.attention_equipment + ' em atenção';

        document.querySelector('#master-rounds').innerHTML = snapshot.rounds.map((round) => `
            <a class="master-round ${round.id === snapshot.display_round_id ? 'current' : ''} ${round.is_overdue ? 'overdue' : ''}" href="/leituras?round=${round.id}">
                <strong>${escapeHtml(round.time)}</strong>
                <span class="round-blocks">${round.sections.map((section) => `<i class="${escapeHtml(section.status)}" title="${escapeHtml(section.label)}"></i>`).join('')}</span>
                <small>${round.status === 'completed' ? 'Concluída' : round.is_overdue ? 'Atrasada ' + relativeTime(round.scheduled_at) : round.progress + '% concluída'}</small>
            </a>
        `).join('');

        document.querySelector('#alerts-list').innerHTML = snapshot.alerts.length
            ? snapshot.alerts.map((alert) => `<div class="list-row"><i class="list-mark alert"></i><div><b>${escapeHtml(alert.section)} • ${escapeHtml(alert.field_key)}</b><small>${escapeHtml(alert.value)} ${escapeHtml(alert.unit || '')} • faixa ${escapeHtml(alert.minimum ?? '—')}–${escapeHtml(alert.maximum ?? '—')}</small></div><time>${escapeHtml(alert.operator || '')}<br>${relativeTime(alert.recorded_at)}</time></div>`).join('')
            : '<div class="empty-state">Nenhum parâmetro fora da faixa.</div>';

        document.querySelector('#activities-list').innerHTML = snapshot.activities.length
            ? snapshot.activities.map((activity) => `<div class="list-row"><i class="list-mark"></i><div><b>${escapeHtml(activity.title || 'Atividade registrada')}</b><small>${escapeHtml(activity.operator || 'Sistema')}</small></div><time>${relativeTime(activity.created_at)}</time></div>`).join('')
            : '<div class="empty-state">As alterações dos operadores aparecerão aqui.</div>';
    }

    async function refreshMaster() {
        const { response, data } = await api('/api/operation-snapshot');
        if (response.ok) renderSnapshot(data);
        else if (!content.hidden) toast('Falha temporária ao atualizar o painel.', true);
    }

    refreshMaster();
    setInterval(refreshMaster, 5000);
}


if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
