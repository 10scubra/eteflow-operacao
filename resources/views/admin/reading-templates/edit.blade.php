@extends('layouts.app')
@section('title', 'Editar modelo • ETEFlow')
@section('page', 'reading-builder')
@section('content')
<div class="app-shell">
@include('operation.navigation')
<main class="workspace">
<header class="page-header">
    <div><span class="eyebrow">{{ $version->template->name }}</span><h1>Versão {{ $version->version }}</h1><p>{{ $version->status === 'DRAFT' ? 'Rascunho editável' : 'Estrutura histórica imutável' }}</p></div>
    <div class="panel-actions">
        <a class="button secondary" href="{{ route('admin.reading-templates.preview', $version) }}">Visualizar como operador</a>
        <form method="POST" action="{{ route('admin.reading-templates.duplicate', $version) }}">@csrf<button class="button secondary">Duplicar versão</button></form>
        @if($version->status === 'DRAFT')<form method="POST" action="{{ route('admin.reading-templates.publish', $version) }}">@csrf<button class="button primary">Publicar</button></form>@endif
    </div>
</header>
@if(session('success'))<div class="inline-alert">{{ session('success') }}</div>@endif
@if($errors->any())<div class="form-alert">{{ $errors->first() }}</div>@endif
@if($version->status !== 'DRAFT')<div class="inline-alert">Esta versão é histórica e não pode ser alterada. Use “Duplicar versão” para criar um rascunho editável.</div>@endif

@if($version->status === 'DRAFT')
<section class="round-card">
    <h2>Adicionar seção</h2>
    <form method="POST" action="{{ route('admin.reading-templates.sections.store', $version) }}" class="builder-form compact">@csrf
        <input name="label" placeholder="Nome da seção" required>
        <input name="section_key" pattern="[a-z0-9_]+" placeholder="chave_estavel" required>
        <input type="number" name="sort_order" value="0" min="0" required>
        <button class="button primary">Adicionar</button>
    </form>
</section>
@endif

<div class="builder-cards">
@forelse($version->sections as $section)
<article class="round-card">
    <div class="panel-title"><div><span class="eyebrow">SEÇÃO {{ $section->sort_order }}</span><h2>{{ $section->label }}</h2><p>{{ $section->section_key }}</p></div><span class="status-badge completed">{{ $section->parameterRules->count() }} parâmetros</span></div>
    @if($version->status === 'DRAFT')
    <details class="builder-details"><summary>Editar seção e ordem</summary>
        <form method="POST" action="{{ route('admin.reading-templates.sections.update',$section) }}" class="builder-form">@csrf @method('PUT')
            <div class="form-row"><label>Nome<input name="label" value="{{ $section->label }}" required></label><label>Chave estável<input name="section_key" value="{{ $section->section_key }}" pattern="[a-z0-9_]+" required></label><label>Ordem<input type="number" name="sort_order" value="{{ $section->sort_order }}" min="0" required></label></div>
            <label>Descrição<textarea name="description">{{ $section->description }}</textarea></label>
            <input type="hidden" name="is_active" value="0"><label class="check-line"><input type="checkbox" name="is_active" value="1" @checked($section->is_active)> Seção ativa</label>
            <button class="button primary">Salvar seção</button>
        </form>
    </details>
    @endif
    <div class="parameter-cards">
    @foreach($section->parameterRules as $rule)
        <div class="field-card">
            <b>{{ $rule->label }}</b>
            <small>{{ $definitionLabels['data_types'][$rule->data_type] ?? 'Legado' }}{{ $rule->unit ? ' • '.$rule->unit : '' }} • {{ $definitionLabels['frequencies'][$rule->frequency_type] ?? 'Frequência legada' }}</small>
            @if($rule->points->where('is_active',true)->isNotEmpty())<p>{{ $rule->points->where('is_active',true)->pluck('label')->join(' · ') }}</p>@endif
            @if($version->status === 'DRAFT')
            <details class="builder-details">
                <summary>Editar parâmetro</summary>
                <form method="POST" action="{{ route('admin.reading-templates.parameters.update',$rule) }}" class="builder-form">@csrf @method('PUT')
                    <div class="form-row"><label>Nome<input name="label" value="{{ $rule->label }}" required></label><label>Chave<input name="field_key" pattern="[a-z0-9_]+" value="{{ $rule->field_key }}" required></label></div>
                    <div class="form-row"><label>Tipo<select name="data_type">@foreach($dataTypes as $type)<option value="{{ $type }}" @selected($rule->data_type===$type)>{{ $definitionLabels['data_types'][$type] ?? $type }}</option>@endforeach</select></label><label>Equipamento relacionado<select name="equipment_id"><option value="">Nenhum</option>@foreach($equipment as $item)<option value="{{ $item->id }}" @selected($rule->equipment_id===$item->id)>{{ $item->name }}</option>@endforeach</select></label><label>Unidade<input name="unit" value="{{ $rule->unit }}"></label><label>Casas<input type="number" name="decimal_places" value="{{ $rule->decimal_places ?? 2 }}" min="0" max="6"></label></div>
                    <div class="form-row"><label>Condição<select name="condition_operator">@foreach($conditionOperators as $operator)<option value="{{ $operator }}" @selected($rule->condition_operator===$operator)>{{ $definitionLabels['conditions'][$operator] ?? $operator }}</option>@endforeach</select></label><label>Mínimo<input type="number" step="any" name="minimum_value" value="{{ $rule->minimum_value }}"></label><label>Máximo<input type="number" step="any" name="maximum_value" value="{{ $rule->maximum_value }}"></label><label>Referência<input type="number" step="any" name="reference_value" value="{{ $rule->reference_value }}"></label></div>
                    <div class="form-row"><label>Frequência<select name="frequency_type">@foreach($frequencyTypes as $frequency)<option value="{{ $frequency }}" @selected($rule->frequency_type===$frequency)>{{ $definitionLabels['frequencies'][$frequency] ?? $frequency }}</option>@endforeach</select></label><label>Horários específicos<small>Ex.: 09:00, 12:00</small><input name="frequency_times_text" value="{{ implode(', ',data_get($rule->frequency_config,'times',[])) }}"></label><label>Intervalo em horas<input type="number" name="frequency_interval_hours" min="1" max="24" value="{{ data_get($rule->frequency_config,'hours') }}"></label><label>Ordem<input type="number" name="sort_order" value="{{ $rule->sort_order }}" min="0" required></label><label class="check-line"><input type="checkbox" name="is_required" value="1" @checked($rule->is_required)> Obrigatório quando aplicável</label></div>
                    <fieldset class="conditional-config"><legend>Exibição condicional (opcional)</legend><div class="form-row"><label>Mostrar quando<select name="visibility_config[field_key]"><option value="">Sempre visível</option>@foreach($section->parameterRules->where('id','!=',$rule->id) as $controllerRule)<option value="{{ $controllerRule->field_key }}" @selected(data_get($rule->visibility_config,'field_key')===$controllerRule->field_key)>{{ $controllerRule->label }}</option>@endforeach</select></label><label>Operador<select name="visibility_config[operator]"><option value="EQUALS">Igual a</option></select></label><label>Valor<select name="visibility_config[value]"><option value="1" @selected(data_get($rule->visibility_config,'value')===true)>Sim</option><option value="0" @selected(data_get($rule->visibility_config,'value')===false)>Não</option></select></label></div></fieldset>
                    <label>Opções de seleção<small>Uma opção por linha.</small><textarea name="options_text">{{ implode("\n",$rule->options ?? []) }}</textarea></label>
                    <label>Regra operacional<textarea name="operational_rule">{{ $rule->operational_rule }}</textarea></label>
                    <details><summary>Pontos de medição</summary><div data-points-editor>@foreach($rule->points->where('is_active',true) as $existingPoint)<div class="form-row point-editor-row"><input data-point-field="label" value="{{ $existingPoint->label }}" placeholder="Nome do ponto" required><input data-point-field="stable_key" value="{{ $existingPoint->stable_key }}" placeholder="chave_estavel" required><input data-point-field="sort_order" type="number" value="{{ $existingPoint->sort_order }}" min="0" required><input data-point-field="instruction" value="{{ $existingPoint->instruction }}" placeholder="Instrução"><button type="button" class="button danger" data-remove-point>Remover</button></div>@endforeach</div><button type="button" class="button secondary" data-add-point>+ Adicionar ponto</button></details>
                    <button class="button primary">Salvar parâmetro</button>
                </form>
            </details>
            @endif
        </div>
    @endforeach
    </div>

    @if($version->status === 'DRAFT')
    <details class="builder-details">
        <summary>Adicionar parâmetro</summary>
        <form method="POST" action="{{ route('admin.reading-templates.parameters.store', $section) }}" class="builder-form">@csrf
            <div class="form-row"><label>Nome<input name="label" required></label><label>Chave<input name="field_key" pattern="[a-z0-9_]+" required></label></div>
            <div class="form-row"><label>Tipo<select name="data_type">@foreach($dataTypes as $type)<option value="{{ $type }}">{{ $definitionLabels['data_types'][$type] ?? $type }}</option>@endforeach</select></label><label>Equipamento relacionado<select name="equipment_id"><option value="">Nenhum</option>@foreach($equipment as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label><label>Unidade<input name="unit" placeholder="%, mg/L, rpm..."></label><label>Casas<input type="number" name="decimal_places" value="2" min="0" max="6"></label></div>
            <div class="form-row"><label>Condição<select name="condition_operator">@foreach($conditionOperators as $operator)<option value="{{ $operator }}">{{ $definitionLabels['conditions'][$operator] ?? $operator }}</option>@endforeach</select></label><label>Mínimo<input type="number" step="any" name="minimum_value"></label><label>Máximo<input type="number" step="any" name="maximum_value"></label><label>Referência<input type="number" step="any" name="reference_value"></label></div>
            <div class="form-row"><label>Frequência<select name="frequency_type">@foreach($frequencyTypes as $frequency)<option value="{{ $frequency }}">{{ $definitionLabels['frequencies'][$frequency] ?? $frequency }}</option>@endforeach</select></label><label>Horários específicos<small>Ex.: 09:00, 12:00</small><input name="frequency_times_text"></label><label>Intervalo em horas<input type="number" name="frequency_interval_hours" min="1" max="24"></label><label>Ordem<input type="number" name="sort_order" value="0" min="0" required></label><label class="check-line"><input type="checkbox" name="is_required" value="1"> Obrigatório quando aplicável</label></div>
            <fieldset class="conditional-config"><legend>Exibição condicional (opcional)</legend><div class="form-row"><label>Mostrar quando<select name="visibility_config[field_key]"><option value="">Sempre visível</option>@foreach($section->parameterRules as $controllerRule)<option value="{{ $controllerRule->field_key }}">{{ $controllerRule->label }}</option>@endforeach</select></label><label>Operador<select name="visibility_config[operator]"><option value="EQUALS">Igual a</option></select></label><label>Valor<select name="visibility_config[value]"><option value="1">Sim</option><option value="0">Não</option></select></label></div></fieldset>
            <label>Opções de seleção<small>Uma opção por linha.</small><textarea name="options_text"></textarea></label>
            <label>Regra operacional<textarea name="operational_rule"></textarea></label>
            <details><summary>Pontos de medição (opcional)</summary><div data-points-editor></div><button type="button" class="button secondary" data-add-point>+ Adicionar ponto</button></details>
            <button class="button primary">Adicionar parâmetro</button>
        </form>
    </details>
    @endif
</article>
@empty
<div class="empty-state">Adicione a primeira seção.</div>
@endforelse
</div>
<template id="point-editor-template"><div class="form-row point-editor-row"><input data-point-field="label" placeholder="Nome do ponto" required><input data-point-field="stable_key" pattern="[a-z0-9_]+" placeholder="chave_estavel" required><input data-point-field="sort_order" type="number" min="0" required><input data-point-field="instruction" placeholder="Instrução"><button type="button" class="button danger" data-remove-point>Remover</button></div></template>
</main>
</div>
@endsection
