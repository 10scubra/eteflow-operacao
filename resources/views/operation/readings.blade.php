@extends('layouts.app')

@section('title', 'Leituras • ETEFlow')
@section('page', 'readings')

@section('content')
<div class="app-shell">
    @include('operation.navigation')

    <main class="workspace">
        <header class="page-header">
            <div>
                <span class="eyebrow">TURNO {{ str_starts_with($shift->starts_at, '20:') ? 'NOTURNO' : 'DIURNO' }} • {{ substr($shift->starts_at, 0, 5) }}–{{ substr($shift->ends_at, 0, 5) }}</span>
                <h1>Monitoramento Diário de Campo — ETE</h1>
                <p>{{ $shift->shift_date->format('d/m/Y') }} • dados compartilhados com a equipe</p>
            </div>
            <div class="server-clock"><span>HORÁRIO DO SERVIDOR</span><strong id="live-clock">--:--:--</strong><small id="live-date"></small></div>
        </header>

        <section class="shift-summary">
            <div><span class="status-dot"></span><b>Turno {{ $shift->status === 'active' ? 'ativo' : ($shift->status === 'planned' ? 'programado' : 'encerrado') }}</b></div>
            <span>@foreach($shift->members->take(2) as $member){{ $member->employee?->display_name ?: $member->name }}{{ $member->employee?->registration_number ? ' • Matrícula '.$member->employee->registration_number : '' }}{{ ! $loop->last ? ' + ' : '' }}@endforeach</span>
            @if($timeline['current'])
                <span>Rodada atual: <b>{{ $timeline['current']->scheduled_at->format('H:i') }}</b></span>
            @elseif($timeline['next'])
                <span>Próxima rodada: <b>{{ $timeline['next']->scheduled_at->format('H:i') }}</b></span>
            @else
                <span>Última rodada do turno</span>
            @endif
        </section>

        <nav class="round-strip" aria-label="Horários das rodadas">
            @foreach($timeline['rounds'] as $round)
                @php
                    $done = $round->sections->where('status', 'completed')->count();
                    $isSelected = $round->id === $selectedRound->id;
                    $isOverdue = $round->scheduled_at->isPast() && $done < $round->sections->count();
                @endphp
                <a href="{{ route('readings', ['round' => $round->id]) }}" class="round-chip {{ $isSelected ? 'active' : '' }} {{ $isOverdue ? 'overdue' : '' }}">
                    <b>{{ $round->scheduled_at->format('H:i') }}</b>
                    <small>{{ $done }}/{{ $round->sections->count() }} blocos</small>
                </a>
            @endforeach
        </nav>

        <section class="round-card">
            <div class="round-heading">
                <div>
                    <span class="eyebrow">RODADA SELECIONADA</span>
                    <h2>{{ $selectedRound->scheduled_at->format('H:i') }}</h2>
                    <p>{{ $selectedRound->scheduled_at->translatedFormat('l, d \d\e F') }}</p>
                </div>
                <div class="progress-copy"><strong id="round-progress">{{ $selectedRound->sections->where('status', 'completed')->count() }}/{{ $selectedRound->sections->count() }}</strong><span>blocos concluídos</span></div>
            </div>
            <div class="progress"><i id="progress-bar" style="width: {{ ($selectedRound->sections->where('status', 'completed')->count() / max(1, $selectedRound->sections->count())) * 100 }}%"></i></div>

            <div class="section-tabs" role="tablist">
                @foreach($selectedRound->sections as $section)
                    <button type="button" class="section-tab {{ $loop->first ? 'active' : '' }}" data-section-id="{{ $section->id }}" data-status="{{ $section->status }}">
                        <span>{{ $section->label }}</span>
                        <i></i>
                    </button>
                @endforeach
            </div>
        </section>

        <section id="reading-panel" class="reading-panel" aria-live="polite">
            <div class="loading-card">Carregando bloco…</div>
        </section>
        <script type="application/json" id="reading-definition-labels">@json($definitionLabels)</script>
    </main>

</div>
@endsection
