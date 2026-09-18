@extends('layouts.app')
@php($editing=$employee->exists)
@section('title', ($editing?'Editar':'Novo').' colaborador • ETEFlow')
@section('page', 'admin-employee-form')
@section('content')
<div class="app-shell">@include('operation.navigation')
<main class="workspace admin-workspace">
<header class="page-header"><div><span class="eyebrow">ADMINISTRAÇÃO • COLABORADORES</span><h1>{{ $editing ? 'Editar colaborador' : 'Novo colaborador' }}</h1><p>O acesso ao sistema é opcional e administrado separadamente.</p></div><a class="button secondary" href="{{ $editing ? route('admin.employees.show',$employee) : route('admin.employees.index') }}">Voltar</a></header>
@if($errors->any())<div class="form-alert">{{ $errors->first() }}</div>@endif
<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('admin.employees.update',$employee) : route('admin.employees.store') }}" class="panel-card admin-form">@csrf @if($editing)@method('PUT')@endif
<div class="form-section"><div><span class="eyebrow accent">IDENTIFICAÇÃO</span><h2>Dados profissionais</h2></div><div class="form-grid">
<label class="field span-2"><span>Nome completo *</span><input name="full_name" required value="{{ old('full_name',$employee->full_name) }}"></label>
<label class="field"><span>Nome de exibição *</span><input name="display_name" required value="{{ old('display_name',$employee->display_name) }}"></label>
<label class="field"><span>Matrícula</span><input name="registration_number" value="{{ old('registration_number',$employee->registration_number) }}"></label>
<label class="field"><span>Cargo</span><input name="job_title" value="{{ old('job_title',$employee->job_title) }}"></label>
<label class="field"><span>Função operacional</span><input name="operational_function" value="{{ old('operational_function',$employee->operational_function) }}"></label>
<label class="field"><span>Setor</span><input name="department" value="{{ old('department',$employee->department) }}"></label>
<label class="field"><span>Unidade</span><input name="unit" value="{{ old('unit',$employee->unit) }}"></label>
<label class="field"><span>E-mail corporativo</span><input type="email" name="corporate_email" value="{{ old('corporate_email',$employee->corporate_email) }}"></label>
<label class="field"><span>Data de admissão</span><input type="date" name="hired_at" value="{{ old('hired_at',$employee->hired_at?->format('Y-m-d')) }}"></label>
<label class="field"><span>Status *</span><select name="status" required>@foreach(['active'=>'Ativo','away'=>'Afastado','vacation'=>'Férias','terminated'=>'Desligado','inactive'=>'Inativo'] as $key=>$label)<option value="{{ $key }}" @selected(old('status',$employee->status ?: 'active')===$key)>{{ $label }}</option>@endforeach</select></label>
<label class="field"><span>Foto</span><input type="file" name="photo" accept="image/*"></label>
<label class="field"><span>Data de desligamento</span><input type="date" name="terminated_at" value="{{ old('terminated_at',$employee->terminated_at?->format('Y-m-d')) }}"></label>
</div></div>
@if($canSensitive)<div class="form-section sensitive-section"><div><span class="eyebrow">ACESSO RESTRITO</span><h2>Dados pessoais e administrativos</h2></div><div class="form-grid">
<label class="field"><span>E-mail pessoal</span><input type="email" name="personal_email" value="{{ old('personal_email',$employee->personal_email) }}"></label>
<label class="field"><span>Telefone pessoal</span><input name="phone" value="{{ old('phone',$employee->phone) }}"></label>
<label class="field span-2"><span>Observações administrativas</span><textarea name="administrative_notes">{{ old('administrative_notes',$employee->administrative_notes) }}</textarea></label>
<label class="field span-2"><span>Motivo do desligamento</span><textarea name="termination_reason">{{ old('termination_reason',$employee->termination_reason) }}</textarea></label>
</div></div>@endif
<div class="form-actions"><a class="button secondary" href="{{ route('admin.employees.index') }}">Cancelar</a><button class="button primary large">{{ $editing ? 'Salvar alterações' : 'Cadastrar colaborador' }}</button></div>
</form>
</main></div>
@endsection
