@extends('layouts.app')

@section('title', match($module) {
    'home' => 'Operação',
    'actions' => 'Ações',
    'aspersion' => 'Aspersão',
    'occurrences' => 'Ocorrências',
    'handover' => 'Passagem de turno',
    default => 'Mais opções',
}.' • ETEFlow')
@section('page', 'module')

@section('content')
<div class="app-shell">
    @include('operation.navigation')
    <main class="workspace">
        <header class="page-header">
            <div>
                <span class="eyebrow">TURNO {{ str_starts_with($shift->starts_at, '20:') ? 'NOTURNO' : 'DIURNO' }} • {{ substr($shift->starts_at,0,5) }}–{{ substr($shift->ends_at,0,5) }}</span>
                <h1>@if($module === 'home') Controle operacional @elseif($module === 'actions') Ações do turno @elseif($module === 'aspersion') Controle de aspersão @elseif($module === 'occurrences') Ocorrências @elseif($module === 'handover') Passagem de turno @else Mais opções @endif</h1>
                <p>{{ auth()->user()->name }} • {{ now()->translatedFormat('l, d \d\e F') }}</p>
            </div>
            <div class="server-clock"><span>HORÁRIO DO SERVIDOR</span><strong id="live-clock">--:--:--</strong><small id="live-date"></small></div>
        </header>

        @if(session('success'))<div class="success-alert">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="form-alert">{{ $errors->first() }}</div>@endif

        @if($module === 'home')
            <section class="operator-hero">
                <div><span class="eyebrow accent">TURNO {{ str_starts_with($shift->starts_at, '20:') ? 'NOTURNO' : 'DIURNO' }}</span><h2>Olá, {{ auth()->user()->name }}.</h2><p>Você está trabalhando com {{ $shift->members->where('id','!=',auth()->id())->pluck('name')->join(' + ') ?: 'a equipe do turno' }}.</p></div>
                <div class="hero-status"><span class="live-dot"></span><b>Operação acompanhada</b><small>MySQL sincronizado</small></div>
            </section>
            <section class="quick-grid">
                <a href="{{ route('readings') }}"><span class="quick-icon blue">▦</span><b>Leituras</b><small>{{ $round ? 'Rodada '.$round->scheduled_at->format('H:i') : 'Sem rodada' }}</small><em>→</em></a>
                <a href="{{ route('actions.show') }}"><span class="quick-icon amber">✓</span><b>Ações</b><small>{{ $pendingActions }} pendentes</small><em>→</em></a>
                <a href="{{ route('aspersion') }}"><span class="quick-icon cyan">≈</span><b>Aspersão</b><small>{{ $activeAspersions ? $activeAspersions.' ativa(s)' : 'Nenhuma ativa' }}</small><em>→</em></a>
                <a href="{{ route('occurrences') }}"><span class="quick-icon red">!</span><b>Ocorrências</b><small>{{ $openOccurrences }} abertas</small><em>→</em></a>
            </section>
            @if($round)
                <section class="module-grid">
                    <article class="panel-card">
                        <div class="panel-head"><div><h2>Rodada {{ $round->scheduled_at->format('H:i') }}</h2><p>Progresso compartilhado por bloco</p></div><a href="{{ route('readings', ['round'=>$round->id]) }}">Preencher →</a></div>
                        <div class="block-list">
                            @foreach($round->sections as $section)
                                <div><i class="{{ $section->status }}"></i><span><b>{{ $section->label }}</b><small>{{ match($section->status){'completed'=>'Concluído','in_progress'=>'Em andamento',default=>'Pendente'} }}</small></span></div>
                            @endforeach
                        </div>
                    </article>
                    <article class="panel-card">
                        <div class="panel-head"><div><h2>Equipamentos</h2><p>Situação atual do turno</p></div></div>
                        <div class="list">
                            @foreach($equipment as $item)
                                <div class="list-row"><i class="equipment-dot {{ $item->status }}"></i><div><b>{{ $item->name }}</b><small>{{ $item->location }} @if($item->reading !== null)• {{ number_format($item->reading,1,',','.') }} {{ $item->unit }}@endif</small></div><span class="equipment-label {{ $item->status }}">{{ match($item->status){'operating'=>'Operando','attention'=>'Atenção',default=>'Parado'} }}</span></div>
                            @endforeach
                        </div>
                    </article>
                </section>
            @endif

        @elseif($module === 'actions')
            @if($selectedAction)
                <a class="back-link" href="{{ route('actions.show') }}">← Voltar para ações</a>
                <section class="action-detail">
                    <article class="panel-card">
                        <div class="action-title"><span class="priority {{ $selectedAction->priority }}">{{ strtoupper(match($selectedAction->priority){'low'=>'baixa','medium'=>'média','high'=>'alta',default=>'crítica'}) }}</span><h2>{{ $selectedAction->title }}</h2><p>{{ $selectedAction->description }}</p></div>
                        <div class="meta-grid"><span><b>Equipamento</b>{{ $selectedAction->equipment ?: 'Não informado' }}</span><span><b>Prazo</b>{{ $selectedAction->due_at?->format('d/m • H:i') ?: 'Sem prazo' }}</span><span><b>Status</b>{{ match($selectedAction->status){'pending'=>'Pendente','in_progress'=>'Em andamento','completed'=>'Concluída',default=>'Cancelada'} }}</span></div>
                        @if($selectedAction->status === 'pending')
                            <form method="POST" action="{{ route('actions.start',$selectedAction) }}">@csrf<button class="button primary large">Iniciar execução</button></form>
                        @elseif($selectedAction->status !== 'completed')
                            <form method="POST" enctype="multipart/form-data" action="{{ route('actions.complete',$selectedAction) }}" class="evidence-form">
                                @csrf
                                <h3>Checklist obrigatório</h3>
                                @foreach($selectedAction->checklistItems as $item)
                                    <label class="check-row"><input type="checkbox" name="checklist[]" value="{{ $item->id }}" {{ $item->is_completed ? 'checked' : '' }}><span>{{ $item->label }}</span></label>
                                @endforeach
                                <div class="photo-grid">
                                    @if($selectedAction->requires_before_photo)<label class="upload-card"><span>Foto antes</span><input type="file" name="before_photo" accept="image/*" capture="environment"><small>Toque para fotografar ou escolher</small></label>@endif
                                    @if($selectedAction->requires_after_photo)<label class="upload-card"><span>Foto depois</span><input type="file" name="after_photo" accept="image/*" capture="environment"><small>Toque para fotografar ou escolher</small></label>@endif
                                </div>
                                <label class="field"><span>Valor medido (opcional)</span><input type="number" inputmode="decimal" step="any" name="measured_value"></label>
                                <label class="field"><span>Observação da execução</span><textarea name="observation" required placeholder="Descreva o resultado do serviço"></textarea></label>
                                <button class="button primary large">Concluir com evidências</button>
                            </form>
                        @else
                            <div class="completion-card"><b>Concluída por {{ $selectedAction->completedBy?->name }}</b><span>{{ $selectedAction->completed_at?->format('d/m/Y • H:i') }}</span><p>{{ $selectedAction->observation }}</p></div>
                            <div class="evidence-gallery">
                                @foreach($selectedAction->evidences as $evidence)
                                    <figure><img src="{{ route('evidences.show',$evidence) }}" alt="Evidência {{ $evidence->type }}"><figcaption>{{ $evidence->type === 'before' ? 'Antes' : 'Depois' }} • {{ $evidence->user?->name }} • {{ $evidence->taken_at->format('H:i') }}</figcaption></figure>
                                @endforeach
                            </div>
                        @endif
                    </article>
                </section>
            @else
                <section class="module-grid actions-grid">
                    <div class="action-list">
                        @forelse($actions as $action)
                            <a class="action-card {{ $action->priority }}" href="{{ route('actions.show',$action) }}"><span class="priority {{ $action->priority }}">{{ strtoupper(match($action->priority){'low'=>'BAIXA','medium'=>'MÉDIA','high'=>'ALTA',default=>'CRÍTICA'}) }}</span><div><h3>{{ $action->title }}</h3><p>{{ $action->equipment }} • {{ $action->due_at?->format('H:i') ?: 'sem prazo' }}</p></div><strong>{{ match($action->status){'pending'=>'Pendente','in_progress'=>'Em execução','completed'=>'Concluída',default=>'Cancelada'} }}</strong></a>
                        @empty<div class="empty-state">Nenhuma ação neste turno.</div>@endforelse
                    </div>
                    @if(auth()->user()->role === 'master')
                        <article class="panel-card">
                            <div class="panel-head"><div><h2>Nova ação</h2><p>Atribuir atividade ao turno</p></div></div>
                            <form method="POST" action="{{ route('actions.create') }}" class="stack-form">@csrf
                                <label class="field"><span>Título</span><input name="title" required></label>
                                <label class="field"><span>Equipamento/local</span><input name="equipment"></label>
                                <label class="field"><span>Prioridade</span><select name="priority"><option value="medium">Média</option><option value="high">Alta</option><option value="critical">Crítica</option><option value="low">Baixa</option></select></label>
                                <label class="field"><span>Prazo</span><input type="datetime-local" name="due_at"></label>
                                <label class="field"><span>Descrição</span><textarea name="description"></textarea></label>
                                <label class="check-row"><input type="checkbox" name="requires_before_photo" value="1"><span>Exigir foto antes</span></label>
                                <label class="check-row"><input type="checkbox" name="requires_after_photo" value="1"><span>Exigir foto depois</span></label>
                                <button class="button primary">Criar ação</button>
                            </form>
                        </article>
                    @endif
                </section>
            @endif

        @elseif($module === 'aspersion')
            <section class="module-grid">
                <div class="action-list">
                    @forelse($aspersions as $item)
                        <article class="action-card cyan"><span class="status-orb {{ $item->status }}"></span><div><h3>{{ $item->area }} • {{ $item->line }}</h3><p>Iniciada por {{ $item->startedBy?->name }} às {{ $item->started_at->format('H:i') }}</p>@if($item->status === 'completed')<small>Consumo: {{ $item->total_consumption ?? '—' }}</small>@endif</div>
                        @if($item->status === 'active')<form method="POST" action="{{ route('aspersion.end',$item) }}">@csrf<input class="compact-input" type="number" step="any" name="final_reading" placeholder="Leitura final"><button class="button primary">Finalizar</button></form>@else<strong>Concluída</strong>@endif</article>
                    @empty<div class="empty-state">Nenhuma aspersão registrada.</div>@endforelse
                </div>
                <article class="panel-card">
                    <div class="panel-head"><div><h2>Iniciar aspersão</h2><p>Registre local, leitura e responsável</p></div></div>
                    <form method="POST" action="{{ route('aspersion.start') }}" class="stack-form">@csrf
                        <label class="field"><span>Área</span><input name="area" required placeholder="Ex.: Setor Sul"></label>
                        <label class="field"><span>Linha</span><input name="line" placeholder="Ex.: Linha 01"></label>
                        <label class="field"><span>Leitura inicial</span><input type="number" inputmode="decimal" step="any" name="initial_reading"></label>
                        <label class="field"><span>Observação</span><textarea name="notes"></textarea></label>
                        <button class="button primary large">Iniciar aspersão</button>
                    </form>
                </article>
            </section>

        @elseif($module === 'occurrences')
            <section class="module-grid">
                <article class="panel-card">
                    <div class="panel-head"><div><h2>Registrar ocorrência</h2><p>O registro será compartilhado imediatamente</p></div></div>
                    <form method="POST" enctype="multipart/form-data" action="{{ route('occurrences.create') }}" class="stack-form">@csrf
                        <label class="field"><span>Título</span><input name="title" required placeholder="Ex.: ruído anormal na bomba"></label>
                        <label class="field"><span>Equipamento/local</span><input name="location" required></label>
                        <label class="field"><span>Prioridade</span><select name="priority"><option value="medium">Média</option><option value="high">Alta</option><option value="critical">Crítica</option><option value="low">Baixa</option></select></label>
                        <label class="field"><span>Descrição</span><textarea name="description"></textarea></label>
                        <label class="upload-card"><span>Foto opcional</span><input type="file" name="photo" accept="image/*" capture="environment"><small>Fotografar ou escolher arquivo</small></label>
                        <button class="button primary large">Registrar ocorrência</button>
                    </form>
                </article>
                <div class="action-list">
                    @forelse($occurrences as $item)
                        <article class="occurrence-card"><div><span class="priority {{ $item->priority }}">{{ $item->code }}</span><h3>{{ $item->title }}</h3><p>{{ $item->location }} • {{ $item->reporter?->name }} • {{ $item->reported_at->format('H:i') }}</p><small>{{ $item->description }}</small></div>
                        @if($item->photo_path)<img src="{{ route('occurrences.photo',$item) }}" alt="Foto da ocorrência {{ $item->code }}">@endif
                        @if($item->action)<a class="button secondary" href="{{ route('actions.show',$item->action) }}">Abrir ação vinculada</a>@elseif(auth()->user()->role === 'master')<form method="POST" action="{{ route('occurrences.convert',$item) }}">@csrf<button class="button secondary">Transformar em ação</button></form>@endif</article>
                    @empty<div class="empty-state">Nenhuma ocorrência aberta.</div>@endforelse
                </div>
            </section>

        @elseif($module === 'handover')
            <section class="handover-layout">
                <article class="panel-card">
                    <div class="panel-head"><div><h2>Resumo automático</h2><p>Situação que será entregue ao próximo turno</p></div></div>
                    <div class="summary-grid">
                        <div><span>Leituras pendentes</span><b>{{ $summary['pending_reading_sections'] }}</b></div>
                        <div><span>Ações pendentes</span><b>{{ $summary['pending_actions'] }}</b></div>
                        <div><span>Ocorrências abertas</span><b>{{ $summary['open_occurrences'] }}</b></div>
                        <div><span>Aspersões ativas</span><b>{{ $summary['active_aspersions'] }}</b></div>
                    </div>
                </article>
                <article class="panel-card">
                    @if($handover?->confirmed)
                        <div class="completion-card"><b>Passagem confirmada por {{ $handover->confirmedBy?->name }}</b><span>{{ $handover->closed_at?->format('d/m/Y • H:i') }}</span><p>{{ $handover->note ?: 'Sem observação adicional.' }}</p></div>
                    @else
                        <form method="POST" action="{{ route('handover.close') }}" class="stack-form">@csrf
                            <label class="field"><span>Recado para o próximo turno</span><textarea name="note" placeholder="Pendências, cuidados e recomendações"></textarea></label>
                            <label class="check-row"><input type="checkbox" name="confirmed" value="1" required><span>Revisei o resumo e confirmo a passagem do turno.</span></label>
                            <button class="button primary large">Registrar passagem de turno</button>
                        </form>
                    @endif
                </article>
            </section>

        @else
            <section class="hub-grid">
                <a href="{{ route('occurrences') }}"><span class="quick-icon red">!</span><div><span class="eyebrow">REGISTRO E ACOMPANHAMENTO</span><h2>Ocorrências</h2><p>{{ $openOccurrences }} abertas no turno.</p></div><em>→</em></a>
                <a href="{{ route('handover') }}"><span class="quick-icon blue">⇄</span><div><span class="eyebrow">ENCERRAMENTO</span><h2>Passagem de turno</h2><p>Consolidar leituras e pendências.</p></div><em>→</em></a>
                @if(auth()->user()->role === 'master')
                    <a href="{{ route('team.index') }}"><span class="quick-icon cyan">◎</span><div><span class="eyebrow">EQUIPE ATUAL</span><h2>Equipe do turno</h2><p>Selecionar quem está trabalhando agora.</p></div><em>→</em></a>
                    <a href="{{ route('master') }}"><span class="quick-icon green">◫</span><div><span class="eyebrow">GESTÃO</span><h2>Dashboard Master</h2><p>Acompanhar a operação em tempo real.</p></div><em>→</em></a>
                @endif
            </section>
        @endif
    </main>
</div>
@endsection
