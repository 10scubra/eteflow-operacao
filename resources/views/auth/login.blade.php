@extends('layouts.app')

@section('title', 'Entrar • ETEFlow')
@section('page', 'login')

@section('content')
<main class="login-shell">
    <section class="login-brand">
        <div class="brand-mark">EF</div>
        <div>
            <span class="eyebrow">CONTROLE OPERACIONAL</span>
            <h1>ETEFlow</h1>
            <p>Leituras rastreáveis para uma operação segura.</p>
        </div>
    </section>

    <section class="login-card">
        <div class="live-line">
            <span class="live-dot"></span>
            <span id="live-date">Sincronizando horário…</span>
        </div>
        <h2>Acessar o turno</h2>
        <p class="muted">Entre com o usuário definido para este aparelho.</p>

        @if ($errors->any())
            <div class="form-alert">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.store') }}" class="login-form">
            @csrf
            <label>
                <span>Usuário</span>
                <input name="username" value="{{ old('username') }}" autocomplete="username" autocapitalize="none" required autofocus placeholder="Ex.: operador1">
            </label>
            <label>
                <span>Senha</span>
                <input type="password" name="password" autocomplete="current-password" required placeholder="Digite sua senha">
            </label>
            <button class="button primary large" type="submit">Entrar no sistema</button>
        </form>

        <div class="test-devices">
            <span><b>Celular 1</b> operador1</span>
            <span><b>Celular 2</b> operador2</span>
            <span><b>Computador</b> master.teste</span>
        </div>
    </section>
</main>
@endsection
