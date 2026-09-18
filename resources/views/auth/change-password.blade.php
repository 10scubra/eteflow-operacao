@extends('layouts.app')

@section('title', 'Defina sua nova senha • ETEFlow')
@section('page', 'change-password')

@section('content')
<main class="auth-shell">
    <section class="login-card password-card">
        <div class="brand login-brand"><span class="brand-mark">EF</span><span><b>ETEFlow</b><small>Segurança do acesso</small></span></div>
        <span class="eyebrow accent">PRIMEIRO ACESSO</span>
        <h1>Defina sua nova senha</h1>
        <p>A senha temporária precisa ser substituída antes de continuar.</p>
        @if($errors->any())<div class="form-alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('password.update') }}" class="login-form">
            @csrf @method('PUT')
            <label><span>Nova senha</span><input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
            <label><span>Confirme a nova senha</span><input type="password" name="password_confirmation" required minlength="10" autocomplete="new-password"></label>
            <small class="muted-copy">Use ao menos 10 caracteres, com maiúsculas, minúsculas e números.</small>
            <button class="button primary large">Salvar nova senha</button>
        </form>
        <form method="POST" action="{{ route('logout') }}" class="logout-inline">@csrf<button class="button secondary">Sair</button></form>
    </section>
</main>
@endsection
