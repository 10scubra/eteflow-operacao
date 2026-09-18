@extends('layouts.app')
@section('title', 'Preview de leitura • ETEFlow')
@section('page', 'reading-preview')
@section('content')
<div class="app-shell">@include('operation.navigation')<main class="workspace"><header class="page-header"><div><span class="eyebrow">PREVIEW SEM PERSISTÊNCIA</span><h1>{{ $version->template->name }} • v{{ $version->version }}</h1><p>O mesmo renderer usado pela operação. Nada preenchido aqui é salvo.</p></div><a class="button secondary" href="{{ route('admin.reading-templates.edit', $version) }}">Voltar ao construtor</a></header><div id="definition-preview" data-definition='@json($version->sections)'></div><script type="application/json" id="reading-definition-labels">@json($definitionLabels)</script></main></div>
@endsection
