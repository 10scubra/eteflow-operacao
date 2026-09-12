@extends('layouts.app')

@section('title', 'Equipe do turno • ETEFlow')
@section('page', 'team')

@section('content')
<div class="app-shell">
    @include('operation.navigation')

    <main class="workspace">
        <header class="page-header">
            <div>
                <span class="eyebrow">GESTÃO MASTER • TURNO {{ str_starts_with($shift->starts_at, '20:') ? 'NOTURNO' : 'DIURNO' }}</span>
                <h1>Equipe do turno</h1>
                <p>{{ substr($shift->starts_at, 0, 5) }}–{{ substr($shift->ends_at, 0, 5) }} • {{ $shift->shift_date->format('d/m/Y') }}</p>
            </div>
            <div class="server-clock"><span>HORÁRIO DO SERVIDOR</span><strong id="live-clock">--:--:--</strong><small id="live-date"></small></div>
        </header>

        @if(session('success'))
            <div class="success-alert">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="form-alert">{{ $errors->first() }}</div>
        @endif

        <section class="team-layout">
            <article class="panel-card">
                <div class="panel-head">
                    <div>
                        <span class="eyebrow accent">EQUIPE ATIVA</span>
                        <h2>{{ $shift->members->pluck('name')->join(' + ') ?: 'Nenhum operador' }}</h2>
                        <p>Os integrantes compartilham leituras, ações e ocorrências deste turno.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('team.update') }}" class="team-form">
                    @csrf
                    @method('PUT')

                    <fieldset>
                        <legend>Selecione quem está trabalhando agora</legend>
                        <div class="operator-options">
                            @forelse($operators as $operator)
                                <label class="operator-choice">
                                    <input
                                        type="checkbox"
                                        name="operator_ids[]"
                                        value="{{ $operator->id }}"
                                        @checked(in_array($operator->id, old('operator_ids', $shift->members->modelKeys()), true))
                                    >
                                    <span class="avatar">{{ strtoupper(substr($operator->name, 0, 2)) }}</span>
                                    <span>
                                        <b>{{ $operator->name }}</b>
                                        <small>{{ $operator->username }} • Operador ativo</small>
                                    </span>
                                    <i aria-hidden="true">✓</i>
                                </label>
                            @empty
                                <div class="empty-state">Não há operadores ativos disponíveis.</div>
                            @endforelse
                        </div>
                    </fieldset>

                    <div class="team-note">
                        <b>Histórico preservado</b>
                        <p>Quem sair da equipe deixa de editar este turno, mas suas leituras e atividades continuam com autoria e horário.</p>
                    </div>

                    <button class="button primary large" @disabled($operators->isEmpty())>Salvar equipe do turno</button>
                </form>
            </article>

            <article class="panel-card">
                <div class="panel-head">
                    <div>
                        <h2>Participação neste turno</h2>
                        <p>Entradas e saídas registradas</p>
                    </div>
                </div>
                <div class="team-history">
                    @forelse($shift->allMembers->sortBy('pivot.joined_at') as $member)
                        <div class="list-row">
                            <span class="avatar">{{ strtoupper(substr($member->name, 0, 2)) }}</span>
                            <div>
                                <b>{{ $member->name }}</b>
                                <small>Entrou {{ $member->pivot->joined_at ? \Illuminate\Support\Carbon::parse($member->pivot->joined_at)->format('d/m • H:i') : 'sem horário' }}</small>
                            </div>
                            @if($member->pivot->left_at)
                                <span class="equipment-label stopped">Saiu {{ \Illuminate\Support\Carbon::parse($member->pivot->left_at)->format('H:i') }}</span>
                            @else
                                <span class="equipment-label operating">Na equipe</span>
                            @endif
                        </div>
                    @empty
                        <div class="empty-state">A participação será registrada ao salvar a equipe.</div>
                    @endforelse
                </div>
            </article>
        </section>
    </main>
</div>
@endsection
