@extends('layouts.app')

@section('title', 'Assistente de Dosagem • ETEFlow')
@section('page', 'dosing')

@section('content')
<div class="app-shell">
    @include('operation.navigation')
    <main class="workspace">
        <header class="page-header"><div><span class="eyebrow">ASSISTENTE OPERACIONAL</span><h1>Controle de dosagem</h1><p>O sistema orienta; a confirmação permanece com o operador.</p></div><div class="server-clock"><span>HORÁRIO DO SERVIDOR</span><strong id="live-clock">--:--:--</strong><small id="live-date"></small></div></header>
        @if(session('success'))<div class="success-alert">{{ session('success') }}</div>@endif
        @if(session('stop_condition'))<div class="form-alert"><b>ATENÇÃO — CONDIÇÃO DE VERIFICAÇÃO ATINGIDA</b><br>{{ session('stop_condition') }} Verifique a necessidade de interromper conforme o procedimento.</div>@endif
        @if($errors->any())<div class="form-alert">{{ $errors->first() }}</div>@endif

        @if($consistency)
            <section class="panel-card">
                <div class="panel-head"><div><h2>pH — Lagoa Aeróbia</h2><p>Análise informativa dos pontos da mesma rodada</p></div></div>
                <div class="block-list">
                    @foreach($consistency->values_snapshot as $point)
                        <div><i class="{{ $point['point_id'] === $consistency->control_point_id ? 'in_progress' : 'completed' }}"></i><span><b>{{ $point['label'] }} — {{ number_format($point['value'],2,',','.') }}</b><small>{{ $point['point_id'] === $consistency->control_point_id ? 'PONTO FIXO DE CONTROLE' : 'Leitura complementar' }}</small></span></div>
                    @endforeach
                </div>
                <div class="meta-grid"><span><b>Média informativa</b>{{ number_format($consistency->average_value,2,',','.') }}</span><span><b>Amplitude</b>{{ number_format($consistency->spread_value,2,',','.') }}</span><span><b>Critério vigente</b>{{ $consistency->configured_max_spread !== null ? number_format($consistency->configured_max_spread,2,',','.') : 'Não configurado' }}</span></div>
                @if($consistency->is_divergent)<div class="form-alert"><b>⚠ Divergência entre os pontos de pH</b><br>As leituras apresentaram diferença superior ao limite configurado. Confira a medição e as condições dos pontos antes de interpretar o conjunto.@if($consistencyRecurrence)<br><b>Possível necessidade de verificação da medição/instrumento.</b>@endif</div>@elseif($consistency->configured_max_spread !== null)<div class="success-alert">✓ Leituras dentro do critério de consistência configurado.</div>@endif
                <p><small>A condição de dosagem é avaliada somente pelo ponto fixo de controle, nunca pela média.</small></p>
            </section>
        @endif

        @if($cycle)
            @php($lastPercentage = $cycle->events->whereNotNull('percentage')->last()?->percentage ?? $cycle->initial_percentage)
            @php($lastPh = $cycle->events->whereNotNull('ph_value')->last()?->ph_value ?? $cycle->initial_value)
            <section class="operator-hero dosing-active">
                <div><span class="eyebrow accent">DOSAGEM EM ANDAMENTO</span><h2>{{ $cycle->product_name }}</h2><p>Início {{ $cycle->started_at->format('d/m • H:i') }} por {{ $cycle->startedBy->name }}</p></div>
                <div class="hero-status"><b>pH inicial {{ number_format($cycle->initial_value,2,',','.') }}</b><small>Bomba {{ number_format($lastPercentage,0,',','.') }}% • há {{ $cycle->started_at->diffForHumans(short:true) }}</small></div>
            </section>
            @if($recheckDue)<div class="form-alert"><b>NOVA VERIFICAÇÃO DE pH</b><br>A dosagem permanece em andamento. Realize ou verifique uma nova leitura conforme o procedimento operacional.</div>@endif
            <section class="module-grid">
                <article class="panel-card"><h2>Registrar acompanhamento</h2><form method="POST" action="{{ route('dosing.ph',$cycle) }}" class="evidence-form">@csrf<label class="field"><span>Novo pH</span><input name="ph_value" type="number" inputmode="decimal" step="0.01" min="0" max="14" required></label><button class="button primary">Registrar novo pH</button></form><form method="POST" action="{{ route('dosing.percentage',$cycle) }}" class="evidence-form">@csrf<label class="field"><span>Novo percentual da bomba</span><input name="percentage" type="number" inputmode="decimal" step="0.01" min="0" max="100" required></label><button class="button secondary">Alterar percentual</button></form></article>
                <article class="panel-card"><h2>Decisão operacional</h2><p>Último pH registrado: <b>{{ number_format($lastPh,2,',','.') }}</b>. Condição configurada para verificação: {{ $cycle->rule->stop_operator === 'GREATER_THAN_OR_EQUAL' ? '≥' : $cycle->rule->stop_operator }} {{ number_format($cycle->rule->stop_value,2,',','.') }}.</p><form method="POST" action="{{ route('dosing.continue',$cycle) }}" class="evidence-form">@csrf<label class="field"><span>Justificativa para continuar</span><textarea name="justification" required></textarea></label><button class="button secondary">Continuar dosagem</button></form><form method="POST" action="{{ route('dosing.finish',$cycle) }}" class="evidence-form">@csrf<label class="field"><span>pH final</span><input name="final_ph" type="number" inputmode="decimal" step="0.01" min="0" max="14" required></label><label class="field"><span>Observação opcional</span><textarea name="notes"></textarea></label><button class="button primary">Finalizar dosagem</button></form></article>
            </section>
            <section class="panel-card"><div class="panel-head"><div><h2>Histórico temporal</h2><p>Nenhum evento substitui o anterior</p></div></div><div class="list">@foreach($cycle->events->sortByDesc('event_occurred_at') as $event)<div class="list-row"><i class="list-mark"></i><div><b>{{ match($event->type){'STARTED'=>'Dosagem iniciada','PERCENTAGE_CHANGED'=>'Percentual alterado','PH_MEASURED'=>'Novo pH','CONTINUED_AFTER_STOP_CONDITION'=>'Decisão de continuar','ENDED'=>'Dosagem finalizada',default=>$event->type} }}</b><small>@if($event->percentage !== null){{ number_format($event->percentage,2,',','.') }}% @endif @if($event->ph_value !== null)• pH {{ number_format($event->ph_value,2,',','.') }} @endif {{ $event->justification }}</small></div><time>{{ $event->creator?->name }}<br>{{ $event->event_occurred_at->format('H:i') }}</time></div>@endforeach</div></section>
        @elseif($alert)
            <section class="operator-hero dosing-warning"><div><span class="eyebrow">{{ $alert->status === 'REMINDER' ? 'ATENÇÃO — DOSAGEM AINDA NÃO REGISTRADA' : 'ATENÇÃO — CONDIÇÃO PARA DOSAGEM' }}</span><h2>{{ $alert->rule->product_name }}</h2><p>pH registrado: {{ number_format($alert->trigger_value,2,',','.') }} • condição {{ $alert->rule->start_operator === 'LESS_THAN_OR_EQUAL' ? '≤' : $alert->rule->start_operator }} {{ number_format($alert->rule->start_value,2,',','.') }}</p><p>Verifique o processo e a necessidade de iniciar a dosagem conforme procedimento operacional.</p></div><div class="hero-status"><b>{{ $alert->detected_at->diffForHumans() }}</b><small>{{ $alert->rule->equipment->name }}</small></div></section>
            @if(auth()->user()->role === 'operator')<section class="module-grid"><article class="panel-card"><h2>Iniciar dosagem</h2><form method="POST" action="{{ route('dosing.start',$alert) }}" class="evidence-form">@csrf<label class="field"><span>Comando atual da bomba (%)</span><input name="percentage" type="number" inputmode="decimal" step="0.01" min="0" max="100" required></label><button class="button primary large">Iniciar dosagem</button></form></article><article class="panel-card"><h2>Informar impedimento</h2><form method="POST" action="{{ route('dosing.impediment',$alert) }}" class="evidence-form">@csrf<label class="field"><span>Motivo</span><select name="impediment_code" required><option value="">Selecione</option>@foreach($impediments as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></label><label class="field"><span>Justificativa</span><textarea name="justification" placeholder="Obrigatória para decisão operacional ou Outro"></textarea></label><button class="button secondary large">Registrar impedimento</button></form></article></section>@endif
        @else
            <div class="empty-state"><h2>Nenhuma condição de dosagem aberta</h2><p>O assistente verificará somente regras ativas e pontos explicitamente configurados.</p></div>
        @endif

        @if(auth()->user()->role === 'master')<section class="panel-card"><div class="panel-head"><div><h2>Histórico</h2><p>Condições, decisões, impedimentos e ciclos preservados</p></div><a href="{{ route('admin.dosing.configure') }}">Configurar regra →</a></div><div class="list">@forelse($history as $item)<div class="list-row"><i class="list-mark {{ in_array($item->status,['OPEN','REMINDER']) ? 'alert' : '' }}"></i><div><b>{{ $item->rule->product_name }} • pH {{ number_format($item->trigger_value,2,',','.') }}</b><small>{{ $item->status }} @if($item->impediment_code)• {{ $impediments[$item->impediment_code] ?? $item->impediment_code }}@endif @if($item->justification)• {{ $item->justification }}@endif</small></div><time>{{ $item->actionedBy?->name ?: $item->triggerValue?->recordedBy?->name }}<br>{{ $item->detected_at->format('d/m H:i') }}</time></div>@empty<div class="empty-state">Nenhum evento de dosagem registrado.</div>@endforelse</div></section>@endif
    </main>
</div>
@endsection
