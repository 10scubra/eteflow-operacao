@extends('layouts.app')
@section('title', $employee->display_name.' • ETEFlow')
@section('page', 'admin-employee-show')
@section('content')
<div class="app-shell">@include('operation.navigation')
<main class="workspace admin-workspace">
<header class="page-header"><div><span class="eyebrow">COLABORADOR</span><h1>{{ $employee->display_name }}</h1><p>{{ $employee->job_title ?: $employee->operational_function }} {{ $employee->registration_number ? '• Matrícula '.$employee->registration_number : '' }}</p></div><div class="card-actions"><a class="button secondary" href="{{ route('admin.employees.edit',$employee) }}">Editar</a><a class="button primary" href="{{ route('admin.employees.access.show',$employee) }}">Acesso</a></div></header>
@if(session('success'))<div class="success-alert">{{ session('success') }}</div>@endif
<section class="detail-grid">
<article class="panel-card"><span class="eyebrow accent">DADOS</span><h2>{{ $employee->full_name }}</h2><dl class="detail-list"><div><dt>Função</dt><dd>{{ $employee->operational_function ?: '—' }}</dd></div><div><dt>Setor</dt><dd>{{ $employee->department ?: '—' }}</dd></div><div><dt>Unidade</dt><dd>{{ $employee->unit ?: '—' }}</dd></div><div><dt>E-mail corporativo</dt><dd>{{ $employee->corporate_email ?: '—' }}</dd></div>@if($canSensitive)<div><dt>E-mail pessoal</dt><dd>{{ $employee->personal_email ?: '—' }}</dd></div><div><dt>Telefone pessoal</dt><dd>{{ $employee->phone ?: '—' }}</dd></div>@endif</dl></article>
<article class="panel-card"><span class="eyebrow accent">ACESSO</span><h2>{{ $employee->user ? ($employee->user->is_active ? ($employee->user->blocked_at ? 'Bloqueado' : 'Ativo') : 'Desativado') : 'Sem acesso' }}</h2>@if($employee->user)<dl class="detail-list"><div><dt>Usuário</dt><dd>{{ $employee->user->username }}</dd></div><div><dt>Perfil</dt><dd>{{ $employee->user->roleProfile?->name ?? ucfirst($employee->user->role) }}</dd></div><div><dt>Último acesso</dt><dd>{{ $employee->user->last_login_at?->format('d/m/Y • H:i') ?? 'Nunca' }}</dd></div></dl>@else<p class="muted-copy">Este colaborador ainda não possui credencial.</p>@endif</article>
<article class="panel-card"><span class="eyebrow accent">SITUAÇÃO ATUAL</span><h2>{{ ['active'=>'Ativo','away'=>'Afastado','vacation'=>'Férias','terminated'=>'Desligado','inactive'=>'Inativo'][$employee->status] ?? $employee->status }}</h2><dl class="detail-list"><div><dt>Admissão</dt><dd>{{ $employee->hired_at?->format('d/m/Y') ?? '—' }}</dd></div><div><dt>Desligamento</dt><dd>{{ $employee->terminated_at?->format('d/m/Y') ?? '—' }}</dd></div>@if($canSensitive)<div><dt>Motivo</dt><dd>{{ $employee->termination_reason ?: '—' }}</dd></div><div><dt>Observações</dt><dd>{{ $employee->administrative_notes ?: '—' }}</dd></div>@endif</dl></article>
<article class="panel-card"><span class="eyebrow accent">TURNO ATUAL</span><h2>{{ substr($shift->starts_at,0,5) }}–{{ substr($shift->ends_at,0,5) }}</h2><p>{{ $currentMembership ? 'Participando desde '.$currentMembership->joined_at->format('H:i') : 'Não participa do turno atual.' }}</p><a class="button secondary" href="{{ route('team.index') }}">Administrar equipe</a></article>
</section>
</main></div>
@endsection
