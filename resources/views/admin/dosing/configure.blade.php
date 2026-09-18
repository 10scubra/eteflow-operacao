@extends('layouts.app')
@section('title','Configurar Assistente de Dosagem • ETEFlow')
@section('page','admin-dosing')
@section('content')
<div class="app-shell">
    @include('operation.navigation')
    <main class="workspace">
        <header class="page-header"><div><span class="eyebrow">CONFIGURAÇÃO MASTER</span><h1>Assistente de Dosagem</h1><p>Nova configuração gera uma versão; o histórico anterior é preservado.</p></div></header>
        @if(session('success'))<div class="success-alert">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="form-alert">{{ $errors->first() }}</div>@endif
        <section class="panel-card">
            <form method="POST" action="{{ route('admin.dosing.update') }}" class="evidence-form">
                @csrf @method('PUT')
                <h2>{{ $rule->name }}</h2>
                <p>Parâmetro: {{ $rule->parameterRule->label }}. A média é somente informativa; apenas o ponto de controle selecionado dispara a assistência.</p>
                <label class="check-row"><input type="checkbox" name="is_active" value="1" {{ $rule->is_active ? 'checked':'' }}><span>Regra ativa</span></label>
                <label class="field"><span>Ponto fixo de controle</span><select name="parameter_rule_point_id"><option value="">Selecione antes de ativar</option>@foreach($rule->parameterRule->points->where('is_active',true) as $point)<option value="{{ $point->id }}" @selected($rule->parameter_rule_point_id===$point->id)>{{ $point->label }}</option>@endforeach</select></label>
                <label class="field"><span>Equipamento</span><select name="equipment_id" required>@foreach($equipment as $item)<option value="{{ $item->id }}" @selected($rule->equipment_id===$item->id)>{{ $item->name }}</option>@endforeach</select></label>
                <div class="form-row"><label class="field"><span>Condição de início</span><select name="start_operator"><option value="LESS_THAN_OR_EQUAL" @selected($rule->start_operator==='LESS_THAN_OR_EQUAL')>Menor ou igual a</option><option value="LESS_THAN" @selected($rule->start_operator==='LESS_THAN')>Menor que</option></select></label><label class="field"><span>Valor de início</span><input name="start_value" type="number" step="0.01" value="{{ $rule->start_value }}" required></label></div>
                <div class="form-row"><label class="field"><span>Condição de parada/verificação</span><select name="stop_operator"><option value="GREATER_THAN_OR_EQUAL" @selected($rule->stop_operator==='GREATER_THAN_OR_EQUAL')>Maior ou igual a</option><option value="GREATER_THAN" @selected($rule->stop_operator==='GREATER_THAN')>Maior que</option></select></label><label class="field"><span>Valor</span><input name="stop_value" type="number" step="0.01" value="{{ $rule->stop_value }}" required></label></div>
                <h3>Consistência dos pontos</h3>
                <p>O limite não diagnostica descalibração; apenas solicita conferência das medições.</p>
                <div class="form-row"><label class="field"><span>Amplitude máxima aceitável</span><input name="consistency_max_spread" type="number" step="0.01" min="0.01" value="{{ $rule->consistency_max_spread }}" placeholder="Definir com a operação"></label><label class="field"><span>Janela para recorrência</span><input name="recurrence_window" type="number" min="2" value="{{ $rule->recurrence_window }}" placeholder="Ex.: 4"></label><label class="field"><span>Divergências na janela</span><input name="recurrence_count" type="number" min="2" value="{{ $rule->recurrence_count }}" placeholder="Ex.: 3"></label></div>
                <div class="form-row"><label class="field"><span>Lembrar sem ação após (min)</span><input name="reminder_after_minutes" type="number" min="1" value="{{ $rule->reminder_after_minutes }}" required></label><label class="field"><span>Repetir lembrete (min)</span><input name="reminder_interval_minutes" type="number" min="1" value="{{ $rule->reminder_interval_minutes }}"></label><label class="field"><span>Nova verificação durante dosagem (min)</span><input name="recheck_after_minutes" type="number" min="1" value="{{ $rule->recheck_after_minutes }}"></label></div>
                <button class="button primary large">Salvar nova versão</button>
            </form>
        </section>
    </main>
</div>
@endsection
