@extends('layouts.app')
@section('title', 'Construtor de Leituras • ETEFlow')
@section('page', 'reading-builder')
@section('content')
<div class="app-shell">@include('operation.navigation')<main class="workspace"><header class="page-header"><div><span class="eyebrow">ADMINISTRAÇÃO</span><h1>Construtor de Leituras</h1><p>Modelos versionados para as fichas operacionais.</p></div></header>
@if(session('success'))<div class="inline-alert">{{ session('success') }}</div>@endif
@if($errors->any())<div class="form-alert">{{ $errors->first() }}</div>@endif
<section class="builder-grid"><article class="round-card"><h2>Novo modelo</h2><form method="POST" action="{{ route('admin.reading-templates.store') }}" class="builder-form">@csrf<label>Nome<input name="name" required></label><label>Chave estável<input name="stable_key" pattern="[a-z0-9_]+" required placeholder="ficha_operacional"></label><label>Descrição<textarea name="description"></textarea></label><button class="button primary">Criar modelo</button></form></article>
<section><h2>Modelos</h2><div class="builder-cards">@forelse($templates as $template)<article class="field-card"><div class="panel-title"><div><h3>{{ $template->name }}</h3><p>{{ $template->stable_key }}</p></div><span class="status-badge {{ $template->is_active ? 'completed' : 'pending' }}">{{ $template->is_active ? 'ATIVO' : 'INATIVO' }}</span></div><p>{{ $template->description }}</p><div class="version-list">@foreach($template->versions as $version)<a href="{{ route('admin.reading-templates.edit', $version) }}"><b>Versão {{ $version->version }}</b><span>{{ $version->status }}</span></a>@endforeach</div><form method="POST" action="{{ route('admin.reading-templates.versions.store', $template) }}">@csrf<button class="button secondary">Nova versão vazia</button></form></article>@empty<div class="empty-state">Nenhum modelo criado.</div>@endforelse</div></section></section></main></div>
@endsection
