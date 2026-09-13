@extends('layouts.app')

@section('title', 'Aspersão • '.$point->name)
@section('page', 'public-aspersion')

@section('content')
<main class="public-aspersion-shell">
    <section class="public-aspersion-card">
        <header class="public-aspersion-head">
            <img src="{{ asset('assets/icon.svg') }}" alt="" width="44" height="44">
            <div><span class="eyebrow">ETEFlow • ACESSO RÁPIDO</span><h1>{{ $point->name }}</h1><p>{{ $point->location ?: 'Painel de acionamento' }}</p></div>
        </header>

        @if(session('success'))<div class="success-alert">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="form-alert">{{ $errors->first() }}</div>@endif

        <div class="public-state {{ $activeAspersion ? 'active' : 'stopped' }}">
            <span class="status-orb {{ $activeAspersion ? '' : 'completed' }}"></span>
            <div>
                <small>ESTADO ATUAL</small>
                <strong>{{ $activeAspersion ? 'Aspersão em andamento' : 'Bomba sem aspersão ativa' }}</strong>
                @if($activeAspersion)<span>Iniciada às {{ $activeAspersion->started_at->format('H:i') }} • Totalizador {{ $activeAspersion->initial_reading }}</span>@endif
            </div>
        </div>

        <form method="POST" action="{{ route('aspersion.public.store', $point->public_token) }}" class="stack-form public-aspersion-form">
            @csrf
            <input type="hidden" name="action" value="{{ $activeAspersion ? 'end' : 'start' }}">
            <div class="form-intro">
                <span class="eyebrow accent">{{ $activeAspersion ? 'DESLIGAMENTO' : 'ACIONAMENTO' }}</span>
                <h2>{{ $activeAspersion ? 'Finalizar aspersão' : 'Iniciar aspersão' }}</h2>
                <p>Informe os valores exibidos no painel neste momento.</p>
            </div>
            <label class="field"><span>Leitura do totalizador (m³)</span><input type="number" inputmode="decimal" step="0.001" min="0" name="totalizer" value="{{ old('totalizer') }}" required autofocus></label>
            <label class="field"><span>Vazão atual (m³/h)</span><input type="number" inputmode="decimal" step="0.001" min="0" name="flow_rate" value="{{ old('flow_rate') }}" required></label>
            <label class="field"><span>Canhões ativos</span><input type="number" inputmode="numeric" step="1" min="0" max="100" name="active_cannons" value="{{ old('active_cannons', $activeAspersion ? 0 : '') }}" required></label>
            <label class="field"><span>Observação opcional</span><textarea name="notes" maxlength="1000" placeholder="Somente se houver algo diferente na operação">{{ old('notes') }}</textarea></label>
            <button class="button primary large public-submit">{{ $activeAspersion ? 'Confirmar desligamento' : 'Confirmar acionamento' }}</button>
        </form>

        <footer class="public-aspersion-foot">Horário registrado automaticamente pelo servidor • Esta página não dá acesso às outras áreas do sistema.</footer>
    </section>
</main>
@endsection
