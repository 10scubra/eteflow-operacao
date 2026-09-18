@extends('layouts.app')
@section('title', 'Acesso de '.$employee->display_name.' • ETEFlow')
@section('page', 'admin-access')
@section('content')
<div class="app-shell">
    @include('operation.navigation')
    <main class="workspace admin-workspace">
        <header class="page-header">
            <div><span class="eyebrow">ADMINISTRAÇÃO • ACESSOS</span><h1>{{ $employee->display_name }}</h1><p>Credencial, perfil e exceções individuais</p></div>
            <a class="button secondary" href="{{ route('admin.employees.show', $employee) }}">Voltar ao colaborador</a>
        </header>

        @if(session('success'))
            <div class="success-alert">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="form-alert">{{ $errors->first() }}</div>
        @endif
        @if(session('temporary_password'))
            <div class="temporary-secret"><span>SENHA TEMPORÁRIA • EXIBIDA UMA ÚNICA VEZ</span><strong>{{ session('temporary_password') }}</strong><p>Copie agora e entregue ao usuário por um canal seguro.</p></div>
        @endif

        @if(!$employee->user)
            <article class="panel-card access-create">
                <div class="panel-head"><div><span class="eyebrow accent">NOVO ACESSO</span><h2>Criar credencial</h2><p>O usuário deverá trocar a senha temporária no primeiro login.</p></div></div>
                <form method="POST" action="{{ route('admin.employees.access.store', $employee) }}" class="form-grid">
                    @csrf
                    <label class="field"><span>Username</span><input name="username" required value="{{ old('username') }}" autocomplete="off"></label>
                    <label class="field"><span>E-mail de acesso</span><input type="email" name="email" required value="{{ old('email', $employee->corporate_email) }}"></label>
                    <label class="field"><span>Perfil</span><select name="role_id" required>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                        @endforeach
                    </select></label>
                    <label class="field"><span>Senha temporária</span><input type="password" name="temporary_password" required minlength="10" autocomplete="new-password"></label>
                    <div class="form-actions span-2"><button class="button primary large">Criar acesso</button></div>
                </form>
            </article>
        @else
            @php($user = $employee->user)
            <section class="detail-grid access-overview">
                <article class="panel-card">
                    <span class="eyebrow accent">ESTADO DO ACESSO</span><h2>{{ !$user->is_active ? 'Desativado' : ($user->blocked_at ? 'Bloqueado' : 'Ativo') }}</h2><p>{{ $user->username }} • {{ $user->email }}</p>
                    <div class="card-actions">
                        @if(!$user->is_active)
                            <form method="POST" action="{{ route('admin.employees.access.activate', $employee) }}">@csrf @method('PUT')<button class="button primary">Reativar</button></form>
                        @elseif($user->blocked_at)
                            <form method="POST" action="{{ route('admin.employees.access.unblock', $employee) }}">@csrf @method('PUT')<button class="button primary">Desbloquear</button></form>
                        @else
                            <form method="POST" action="{{ route('admin.employees.access.block', $employee) }}">@csrf @method('PUT')<button class="button secondary">Bloquear</button></form>
                            <form method="POST" action="{{ route('admin.employees.access.deactivate', $employee) }}">@csrf @method('PUT')<button class="button danger">Desativar</button></form>
                        @endif
                    </div>
                </article>
                <article class="panel-card">
                    <span class="eyebrow accent">PERFIL</span><h2>{{ $user->roleProfile?->name ?? ucfirst($user->role) }}</h2>
                    <form method="POST" action="{{ route('admin.employees.access.role', $employee) }}" class="stack-form">@csrf @method('PUT')
                        <label class="field"><span>Perfil funcional</span><select name="role_id">
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" @selected($user->role_id === $role->id)>{{ $role->name }}</option>
                            @endforeach
                        </select></label><button class="button primary">Alterar perfil</button>
                    </form>
                </article>
                <article class="panel-card"><span class="eyebrow accent">SENHA</span><h2>{{ $user->must_change_password ? 'Troca pendente' : 'Senha definida' }}</h2><p>O reset gera uma senha temporária e obriga nova definição no próximo login.</p><form method="POST" action="{{ route('admin.employees.access.password', $employee) }}">@csrf @method('PUT')<button class="button secondary">Gerar nova senha temporária</button></form></article>
                <article class="panel-card"><span class="eyebrow accent">ÚLTIMO ACESSO</span><h2>{{ $user->last_login_at?->format('d/m/Y • H:i') ?? 'Nunca' }}</h2><p>Atualizado somente após autenticação bem-sucedida.</p></article>
            </section>

            <article class="panel-card permissions-panel">
                <div class="panel-head"><div><span class="eyebrow accent">EXCEÇÕES INDIVIDUAIS</span><h2>Permissões do usuário</h2><p>Use apenas quando a regra do perfil não for suficiente.</p></div></div>
                @foreach($permissions as $module => $items)
                    <section class="permission-module">
                        <h3>{{ ucfirst($module) }}</h3>
                        <div class="permission-list">
                            @foreach($items as $permission)
                                @php($activeOverride = $user->permissionOverrides->where('permission_id', $permission->id)->whereNull('effective_until')->sortByDesc('id')->first())
                                @php($state = $activeOverride?->effect ?? 'inherit')
                                <form method="POST" action="{{ route('admin.employees.access.override', [$employee, $permission]) }}" class="permission-row">
                                    @csrf
                                    @method('PUT')
                                    <div><b>{{ $permission->name }}</b><small>{{ $permission->code }}</small></div>
                                    <select name="effect" aria-label="Permissão {{ $permission->name }}">
                                        <option value="inherit" @selected($state === 'inherit')>Herdado do perfil</option>
                                        <option value="allow" @selected($state === 'allow')>Permitido individualmente</option>
                                        <option value="deny" @selected($state === 'deny')>Negado individualmente</option>
                                    </select>
                                    <button class="button secondary">Salvar</button>
                                </form>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </article>
        @endif
    </main>
</div>
@endsection
