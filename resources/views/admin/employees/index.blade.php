@extends('layouts.app')
@section('title', 'Colaboradores • ETEFlow')
@section('page', 'admin-employees')
@section('content')
<div class="app-shell">
@include('operation.navigation')
<main class="workspace admin-workspace">
    <header class="page-header"><div><span class="eyebrow">ADMINISTRAÇÃO</span><h1>Colaboradores</h1><p>Pessoas, situação e acesso ao ETEFlow</p></div><a class="button primary" href="{{ route('admin.employees.create') }}">+ Novo colaborador</a></header>
    @if(session('success'))<div class="success-alert">{{ session('success') }}</div>@endif
    <form method="GET" class="admin-filters panel-card">
        <label class="field"><span>Nome ou matrícula</span><input name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Buscar colaborador"></label>
        <label class="field"><span>Status</span><select name="status"><option value="">Todos</option>@foreach(['active'=>'Ativo','away'=>'Afastado','vacation'=>'Férias','terminated'=>'Desligado','inactive'=>'Inativo'] as $key=>$label)<option value="{{ $key }}" @selected(($filters['status'] ?? '')===$key)>{{ $label }}</option>@endforeach</select></label>
        <label class="field"><span>Acesso</span><select name="access"><option value="">Todos</option><option value="with" @selected(($filters['access'] ?? '')==='with')>Com acesso</option><option value="without" @selected(($filters['access'] ?? '')==='without')>Sem acesso</option></select></label>
        <label class="field"><span>Perfil</span><select name="role_id"><option value="">Todos</option>@foreach($roles as $role)<option value="{{ $role->id }}" @selected((string)($filters['role_id'] ?? '')===(string)$role->id)>{{ $role->name }}</option>@endforeach</select></label>
        <button class="button primary">Filtrar</button><a class="button secondary" href="{{ route('admin.employees.index') }}">Limpar</a>
    </form>
    <section class="employee-grid">
        @forelse($employees as $employee)
        <article class="employee-card panel-card">
            <div class="employee-card-head"><span class="avatar">{{ strtoupper(substr($employee->display_name,0,2)) }}</span><div><h2>{{ $employee->display_name }}</h2><p>{{ $employee->full_name }}</p></div><span class="status-badge {{ $employee->status }}">{{ ['active'=>'Ativo','away'=>'Afastado','vacation'=>'Férias','terminated'=>'Desligado','inactive'=>'Inativo'][$employee->status] ?? $employee->status }}</span></div>
            <div class="employee-summary"><span><small>Matrícula</small><b>{{ $employee->registration_number ?: '—' }}</b></span><span><small>Cargo / função</small><b>{{ $employee->operational_function ?: ($employee->job_title ?: '—') }}</b></span><span><small>Acesso</small><b>{{ $employee->user ? 'Sim' : 'Não' }}</b></span><span><small>Perfil</small><b>{{ $employee->user?->roleProfile?->name ?? ($employee->user ? ucfirst($employee->user->role) : '—') }}</b></span></div>
            <div class="card-actions"><a class="button secondary" href="{{ route('admin.employees.show',$employee) }}">Visualizar</a><a class="button secondary" href="{{ route('admin.employees.edit',$employee) }}">Editar</a><a class="button primary" href="{{ route('admin.employees.access.show',$employee) }}">Administrar acesso</a></div>
        </article>
        @empty<div class="empty-state panel-card">Nenhum colaborador encontrado.</div>@endforelse
    </section>
    <div class="pagination-wrap">{{ $employees->links() }}</div>
</main>
</div>
@endsection
