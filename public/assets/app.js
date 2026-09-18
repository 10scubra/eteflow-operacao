const body = document.body;
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const isMaster = body.dataset.userRole === 'master';
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
    in_progress: 'Rascunho',
    completed: 'Concluído',
    reopened: 'Reaberto',
    not_applicable: 'Não aplicável',
};

const definitionLabelsElement = document.querySelector('#reading-definition-labels');
const definitionLabels = definitionLabelsElement ? JSON.parse(definitionLabelsElement.textContent || '{}') : {};
const semanticOptions = definitionLabels.semantic_statuses || { MEASURED: 'Medido', NO_FLOW: 'Sem vazão', METER_FAULT: 'Medidor com defeito', NOT_MEASURED: 'Leitura não realizada', EQUIPMENT_STOPPED: 'Equipamento parado', NOT_APPLICABLE: 'Não aplicável' };
const equipmentOptions = { OPERATING: 'Operando', STOPPED: 'Parado', MAINTENANCE: 'Em manutenção', FAILURE: 'Falha', UNAVAILABLE: 'Indisponível' };

function renderTypedControl(rule, saved = {}) {
    const type = rule.data_type || ((rule.minimum_value === null && rule.maximum_value === null && rule.unit === null) ? 'textarea' : 'decimal');
    if (type === 'boolean') return `<select data-value-boolean><option value="">Selecione</option><option value="1" ${saved.value_boolean === true ? 'selected' : ''}>Sim</option><option value="0" ${saved.value_boolean === false ? 'selected' : ''}>Não</option></select>`;
    if (type === 'equipment_status') return `<select data-equipment-state><option value="">Selecione</option>${Object.entries(equipmentOptions).map(([value,label]) => `<option value="${value}" ${saved.equipment_state === value ? 'selected' : ''}>${label}</option>`).join('')}</select>`;
    if (type === 'single_select') return `<select data-value-text><option value="">Selecione</option>${(rule.options || []).map(value => `<option ${saved.value_text === value ? 'selected' : ''}>${escapeHtml(value)}</option>`).join('')}</select>`;
    if (type === 'time') return `<input type="time" data-value-text value="${escapeHtml(saved.value_text ?? '')}">`;
    if (type === 'text' || type === 'textarea') return `<textarea data-value-text placeholder="Registre a informação">${escapeHtml(saved.value_text ?? '')}</textarea>`;
    const step = type === 'integer' ? '1' : (1 / (10 ** (rule.decimal_places ?? 2)));
    return `<div class="input-unit"><input inputmode="decimal" type="number" step="${step}" data-value-numeric value="${escapeHtml(saved.value_numeric ?? '')}" placeholder="0">${rule.unit ? `<span>${escapeHtml(rule.unit)}</span>` : ''}</div>`;
}

function renderDefinitionField(rule, savedValues = []) {
    const points = rule.points?.length ? rule.points.filter(point => point.is_active) : [null];
    return points.map(point => {
        const saved = savedValues.find(value => Number(value.parameter_rule_id) === Number(rule.id) && (value.parameter_rule_point_id ?? null) === (point?.id ?? null)) || savedValues.find(value => !point && value.field_key === rule.field_key) || {};
        const condition = rule.condition_operator === 'BETWEEN' ? `${rule.minimum_value ?? '—'} a ${rule.maximum_value ?? '—'} ${rule.unit || ''}` : rule.condition_operator === 'GREATER_THAN' ? `Maior que ${rule.minimum_value} ${rule.unit || ''}` : rule.condition_operator === 'LESS_THAN' ? `Menor que ${rule.maximum_value} ${rule.unit || ''}` : rule.reference_value !== null ? `Referência: ${rule.reference_value} ${rule.unit || ''}` : (rule.operational_rule || rule.data_type || 'Parâmetro');
        const visibility = rule.visibility_config || {};
        return `<div class="field-card field" data-value-entry data-field="${escapeHtml(rule.field_key)}" data-rule-id="${rule.id}" data-point-id="${point?.id || ''}" data-visibility-field="${escapeHtml(visibility.field_key || '')}" data-visibility-value="${escapeHtml(String(visibility.value ?? ''))}" data-condition-operator="${escapeHtml(rule.condition_operator || 'REFERENCE')}" data-minimum="${escapeHtml(rule.minimum_value ?? '')}" data-maximum="${escapeHtml(rule.maximum_value ?? '')}" data-reference="${escapeHtml(rule.reference_value ?? '')}"><span class="field-meta"><b>${escapeHtml(rule.label)}${point ? ' • ' + escapeHtml(point.label) : ''}</b><small>${escapeHtml(condition)}</small></span><div data-conditional-message class="conditional-message" hidden>Não aplicável para a resposta atual. O histórico anterior será preservado.</div><select data-semantic-status>${Object.entries(semanticOptions).map(([value,label]) => `<option value="${value}" ${(saved.semantic_status || 'MEASURED') === value ? 'selected' : ''}>${label}</option>`).join('')}</select>${renderTypedControl(rule, saved)}<small data-condition-feedback></small><input data-justification value="${escapeHtml(saved.justification ?? '')}" placeholder="Justificativa/motivo quando necessário"></div>`;
    }).join('');
}

function activateDefinitionBehaviors(root) {
    const entries = [...root.querySelectorAll('[data-value-entry]')];
    const isOutOfCondition = (entry, value) => {
        const number = Number(value);
        if (value === '' || Number.isNaN(number)) return null;
        const minimum = entry.dataset.minimum === '' ? null : Number(entry.dataset.minimum);
        const maximum = entry.dataset.maximum === '' ? null : Number(entry.dataset.maximum);
        const reference = entry.dataset.reference === '' ? null : Number(entry.dataset.reference);
        switch (entry.dataset.conditionOperator) {
            case 'BETWEEN': return (minimum !== null && number < minimum) || (maximum !== null && number > maximum);
            case 'GREATER_THAN': return minimum !== null && !(number > minimum);
            case 'GREATER_THAN_OR_EQUAL': return minimum !== null && !(number >= minimum);
            case 'LESS_THAN': return maximum !== null && !(number < maximum);
            case 'LESS_THAN_OR_EQUAL': return maximum !== null && !(number <= maximum);
            case 'EQUAL': return reference !== null && number !== reference;
            case 'REFERENCE': return null;
            default: return null;
        }
    };
    const update = () => {
        entries.forEach((entry) => {
            if (!entry.dataset.visibilityField) return;
            const parent = entries.find(candidate => candidate.dataset.field === entry.dataset.visibilityField);
            const boolean = parent?.querySelector('[data-value-boolean]')?.value;
            const applicable = String(boolean === '1') === entry.dataset.visibilityValue;
            entry.dataset.applicable = String(applicable);
            entry.classList.toggle('conditional-inactive', !applicable);
            entry.querySelector('[data-conditional-message]').hidden = applicable;
            entry.querySelectorAll('input,select,textarea').forEach(control => control.disabled = !applicable);
        });
    };
    entries.forEach((entry) => {
        entry.querySelectorAll('input,select,textarea').forEach(control => control.addEventListener('change', update));
        const numeric = entry.querySelector('[data-value-numeric]');
        const feedback = entry.querySelector('[data-condition-feedback]');
        if (numeric && feedback) numeric.addEventListener('input', () => {
            const outside = isOutOfCondition(entry, numeric.value);
            feedback.className = outside === true ? 'condition-feedback outside' : outside === false ? 'condition-feedback inside' : 'condition-feedback reference';
            feedback.textContent = outside === true ? 'Fora da faixa operacional' : outside === false ? '✓ Dentro da faixa operacional' : numeric.value === '' ? '' : 'Valor de referência informativo';
        });
        numeric?.dispatchEvent(new Event('input'));
    });
    update();
}

if (body.dataset.page === 'readings') {
    const panel = document.querySelector('#reading-panel');
    const tabs = [...document.querySelectorAll('.section-tab')];
    let activeSectionId = null;

    async function loadSection(id, force = false) {
        activeSectionId = Number(id);
        tabs.forEach((tab) => tab.classList.toggle('active', Number(tab.dataset.sectionId) === activeSectionId));
        panel.innerHTML = '<div class="loading-card">Carregando bloco…</div>';

        const opened = isMaster
            ? { response: { ok: true, status: 200 }, data: {} }
            : await api('/api/reading-sections/' + activeSectionId + '/open', {
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
        const values = section.values;
        const previousValues = new Map((data.previous_values || []).map((value) => [value.field_key, value]));
        const fields = data.rules.map((rule) => renderDefinitionField(rule, values)).join('');

        panel.innerHTML = `
            <form id="reading-form">
                <div class="panel-title">
                    <div><h2>${escapeHtml(section.label)}</h2><p>${section.last_edited_by ? 'Última edição por ' + escapeHtml(section.last_edited_by.name) + ' ' + relativeTime(section.updated_at) : 'Aguardando primeira leitura'}</p>${isMaster ? '<small>Consulta do Master — somente leitura</small>' : ''}</div>
                    <span class="status-badge ${escapeHtml(section.status)}">${escapeHtml(statusLabels[section.status] || section.status)}</span>
                </div>
                <div id="conflict-area"></div>
                <div class="fields-grid">${fields || '<div class="empty-state">Nenhum parâmetro configurado para este bloco.</div>'}</div>
                <div class="panel-actions" ${isMaster ? 'hidden' : ''}>
                    ${previousValues.size ? '<button class="button ghost" type="button" id="copy-previous">Usar última leitura (' + escapeHtml(data.previous_round_time || '') + ')</button>' : ''}
                    <button class="button secondary" type="submit" value="in_progress">Salvar rascunho</button>
                    <button class="button primary" type="submit" value="completed">Concluir bloco</button>
                </div>
            </form>
        `;
        activateDefinitionBehaviors(panel);
        if (isMaster) {
            panel.querySelectorAll('input, select, textarea, button').forEach((control) => control.disabled = true);
        }

        document.querySelector('#copy-previous')?.addEventListener('click', () => {
            if (!window.confirm('Copiar os valores da rodada ' + (data.previous_round_time || 'anterior') + '? Você poderá revisar antes de salvar.')) return;

            document.querySelectorAll('#reading-form [data-value-entry]').forEach((container) => {
                const previous = previousValues.get(container.dataset.field);
                if (!previous) return;
                const text = container.querySelector('[data-value-text]');
                const numeric = container.querySelector('[data-value-numeric]');
                if (text) text.value = previous.value_text ?? '';
                if (numeric) numeric.value = previous.value_numeric ?? '';
            });
            toast('Valores anteriores copiados. Revise antes de salvar.');
        });

        document.querySelector('#reading-form')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            const submitter = event.submitter;
            const valuesPayload = [...event.currentTarget.querySelectorAll('[data-value-entry]')].filter(container => container.dataset.applicable !== 'false').map((container) => {
                const entry = { field_key: container.dataset.field, parameter_rule_id: Number(container.dataset.ruleId), parameter_rule_point_id: container.dataset.pointId ? Number(container.dataset.pointId) : null, semantic_status: container.querySelector('[data-semantic-status]').value, justification: container.querySelector('[data-justification]').value || null };
                const numeric = container.querySelector('[data-value-numeric]'); const text = container.querySelector('[data-value-text]'); const boolean = container.querySelector('[data-value-boolean]'); const equipment = container.querySelector('[data-equipment-state]');
                if (numeric) entry.value_numeric = numeric.value === '' ? null : Number(numeric.value);
                if (text) entry.value_text = text.value || null;
                if (boolean) entry.value_boolean = boolean.value === '' ? null : boolean.value === '1';
                if (equipment) entry.equipment_state = equipment.value || null;
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

if (body.dataset.page === 'reading-builder') {
    const template = document.querySelector('#point-editor-template');
    const reindex = (editor) => {
        [...editor.querySelectorAll('.point-editor-row')].forEach((row, index) => {
            row.querySelectorAll('[data-point-field]').forEach(input => {
                input.name = `points[${index}][${input.dataset.pointField}]`;
            });
            const order = row.querySelector('[data-point-field="sort_order"]');
            if (order && !order.value) order.value = index + 1;
        });
    };
    document.querySelectorAll('[data-points-editor]').forEach(reindex);
    document.addEventListener('click', (event) => {
        const add = event.target.closest('[data-add-point]');
        const remove = event.target.closest('[data-remove-point]');
        if (add) {
            const editor = add.parentElement.querySelector('[data-points-editor]');
            editor.append(template.content.cloneNode(true));
            reindex(editor);
        }
        if (remove) {
            const editor = remove.closest('[data-points-editor]');
            remove.closest('.point-editor-row').remove();
            reindex(editor);
        }
    });
}

if (body.dataset.page === 'reading-preview') {
    const preview = document.querySelector('#definition-preview');
    const sections = JSON.parse(preview?.dataset.definition || '[]');
    preview.innerHTML = sections.map(section => `<section class="round-card"><div class="panel-title"><div><span class="eyebrow">SEÇÃO</span><h2>${escapeHtml(section.label)}</h2></div></div><div class="fields-grid">${(section.parameter_rules || []).map(rule => renderDefinitionField(rule)).join('')}</div></section>`).join('') || '<div class="empty-state">Versão sem definições.</div>';
    activateDefinitionBehaviors(preview);
    preview.querySelectorAll('input,textarea,select').forEach(control => control.addEventListener('change', () => toast('Preview: nenhum dado será salvo.')));
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
        const stockSummary = document.querySelector('#chemical-stock-summary');
        if (stockSummary && snapshot.chemical_stock) {
            const stock = snapshot.chemical_stock;
            stockSummary.innerHTML = `<article class="kpi"><label>Normal</label><strong>${stock.normal}</strong></article><article class="kpi"><label>Baixo</label><strong>${stock.low}</strong></article><article class="kpi"><label>Crítico</label><strong>${stock.critical}</strong></article><article class="kpi"><label>Sem estoque</label><strong>${stock.empty}</strong><small>${stock.unknown ? stock.unknown + ' sem contagem' : ''}</small></article>`;
        }

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
const stockFilters = document.querySelector('.dashboard-filters');
if (stockFilters && window.matchMedia('(max-width: 900px)').matches) {
    stockFilters.removeAttribute('open');
}
function initializeStockDashboard() {
    const filterForm = document.querySelector('[data-dashboard-filter-form]');
    filterForm?.querySelectorAll('[data-auto-filter]').forEach((select) => {
        select.addEventListener('change', () => {
            document.body.classList.add('dashboard-loading');
            filterForm.requestSubmit();
        });
    });

    document.querySelectorAll('[data-filter-url]').forEach((row) => {
        const navigate = () => {
            document.body.classList.add('dashboard-loading');
            window.location.href = row.dataset.filterUrl;
        };
        row.addEventListener('click', (event) => {
            if (!event.target.closest('a, button, input, select')) navigate();
        });
        row.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                navigate();
            }
        });
    });

    document.querySelectorAll('[data-interactive-stock-chart]').forEach((chart) => {
        const dataElement = chart.querySelector('.stock-chart-data');
        const svg = chart.querySelector('svg');
        const tooltip = chart.querySelector('.chart-hover-tooltip');
        if (!dataElement || !svg || !tooltip) return;
        const data = JSON.parse(dataElement.textContent || '{}');
        const activeSeries = new Set((data.series || []).map((_, index) => index));
        const namespace = 'http://www.w3.org/2000/svg';
        const hoverLine = document.createElementNS(namespace, 'line');
        hoverLine.setAttribute('class', 'chart-hover-line');
        hoverLine.setAttribute('y1', '20');
        hoverLine.setAttribute('y2', '268');
        hoverLine.hidden = true;
        svg.appendChild(hoverLine);

        chart.parentElement?.querySelectorAll('[data-series-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const index = Number(button.dataset.seriesToggle);
                const enabled = activeSeries.has(index);
                enabled ? activeSeries.delete(index) : activeSeries.add(index);
                button.classList.toggle('active', !enabled);
                button.setAttribute('aria-pressed', String(!enabled));
                chart.querySelectorAll(`.series-${index}`).forEach((element) => element.classList.toggle('series-hidden', enabled));
            });
        });

        const hideTooltip = () => { tooltip.hidden = true; hoverLine.hidden = true; };
        svg.addEventListener('mouseleave', hideTooltip);
        svg.addEventListener('mousemove', (event) => {
            if (!data.labels?.length) return;
            const rectangle = svg.getBoundingClientRect();
            const ratio = Math.max(0, Math.min(1, (event.clientX - rectangle.left) / rectangle.width));
            const index = Math.round(ratio * (data.labels.length - 1));
            const viewX = 48 + (index / Math.max(1, data.labels.length - 1)) * 892;
            hoverLine.setAttribute('x1', String(viewX));
            hoverLine.setAttribute('x2', String(viewX));
            hoverLine.hidden = false;
            const rows = (data.series || []).map((series, seriesIndex) => {
                const value = series.values?.[index];
                if (!activeSeries.has(seriesIndex) || value === null || value === undefined) return '';
                return `<span><i class="tooltip-dot color-${seriesIndex % 5}"></i>${escapeHtml(series.name)}<b>${Number(value).toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%</b></span>`;
            }).join('');
            const receipts = Number(data.receipts?.[index] || 0);
            tooltip.innerHTML = `<strong>${escapeHtml(data.labels[index])}</strong>${rows}${receipts ? `<em>${receipts} entrada(s) registrada(s)</em>` : ''}`;
            tooltip.hidden = false;
            const desiredLeft = event.clientX - chart.getBoundingClientRect().left + 14;
            tooltip.style.left = `${Math.min(Math.max(8, desiredLeft), chart.clientWidth - tooltip.offsetWidth - 8)}px`;
            tooltip.style.top = `${Math.max(8, event.clientY - chart.getBoundingClientRect().top - tooltip.offsetHeight - 10)}px`;
        });
    });
}
initializeStockDashboard();

function renderIndicatorCharts() {
  document.querySelectorAll('.indicator-chart[data-series]').forEach((container) => {
    let rows = [];
    try { rows = JSON.parse(container.dataset.series || '[]'); } catch { return; }
    const svg = container.querySelector('svg');
    if (!svg || !rows.length) return;
    const values = rows.map((row) => Number(row.value)).filter(Number.isFinite);
    if (!values.length) return;
    const width = 600, height = 220, pad = 28;
    const min = Math.min(...values), max = Math.max(...values), range = Math.max(0.0001, max - min);
    const points = rows.map((row, index) => {
      const x = pad + (index / Math.max(1, rows.length - 1)) * (width - pad * 2);
      const y = height - pad - ((Number(row.value) - min) / range) * (height - pad * 2);
      return { x, y, row };
    });
    const ns = 'http://www.w3.org/2000/svg';
    [0, .5, 1].forEach((factor) => {
      const line = document.createElementNS(ns, 'line');
      const y = pad + factor * (height - pad * 2);
      line.setAttribute('x1', pad); line.setAttribute('x2', width - pad); line.setAttribute('y1', y); line.setAttribute('y2', y); line.setAttribute('class', 'grid-line');
      svg.appendChild(line);
    });
    const polyline = document.createElementNS(ns, 'polyline');
    polyline.setAttribute('points', points.map((p) => p.x + ',' + p.y).join(' '));
    polyline.setAttribute('class', 'indicator-line');
    svg.appendChild(polyline);
    points.forEach((point) => {
      const circle = document.createElementNS(ns, 'circle');
      circle.setAttribute('cx', point.x); circle.setAttribute('cy', point.y); circle.setAttribute('r', 5); circle.setAttribute('class', 'indicator-point');
      const title = document.createElementNS(ns, 'title');
      title.textContent = new Date(point.row.at).toLocaleString('pt-BR') + ' • ' + point.row.label + ': ' + point.row.value + ' ' + (point.row.unit || '');
      circle.appendChild(title); svg.appendChild(circle);
    });
  });
}
document.addEventListener('DOMContentLoaded', renderIndicatorCharts);