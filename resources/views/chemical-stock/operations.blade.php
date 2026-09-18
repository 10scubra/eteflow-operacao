@extends('layouts.app')
@section('title','Movimentações do estoque • ETEFlow')
@section('page','chemical-stock-operations')
@section('content')
<div class="app-shell">
@include('operation.navigation')
<main class="workspace">
<header class="page-header"><div><span class="eyebrow">ESTOQUE OPERACIONAL</span><h1>Movimentações de produtos químicos</h1><p>Retiradas, transferências, inventário e acompanhamento dos sacos abertos na UAP.</p></div><div class="card-actions">@can('chemical_stock.view_balance')<a class="button secondary" href="{{ route('chemical-stock.dashboard') }}">Dashboard</a>@endcan<a class="button secondary" href="{{ route('chemical-stock.receipt.form') }}">Registrar entrada</a></div></header>
@if(session('success'))<div class="success-alert">{{ session('success') }}</div>@endif
@if($errors->any())<div class="form-alert">{{ $errors->first() }}</div>@endif

<section class="quick-action-grid">
@can('chemical_stock.issue')
<article class="panel-card">
<div class="panel-head"><div><span class="eyebrow">SAÍDA OPERACIONAL</span><h2>Retirar produto</h2><p>Embalagem lacrada é baixada integralmente. Para polímero, retire um saco por registro.</p></div></div>
<form method="POST" action="{{ route('chemical-stock.issue.store') }}" class="stack-form">@csrf
<input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
<label class="field"><span>Produto e local de origem</span><select name="chemical_storage_location_id" required><option value="">Selecione</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('chemical_storage_location_id')==$location->id)>{{ $location->product->name }} — {{ $location->name }} ({{ $location->unit->symbol }})</option>@endforeach</select></label>
<label class="field"><span>Quantidade retirada</span><input type="number" inputmode="decimal" min="0.001" step="any" name="quantity" value="{{ old('quantity',1) }}" required></label>
<label class="field"><span>Destino de uso</span><input name="usage_location" value="{{ old('usage_location','UAP') }}" maxlength="150"></label>
<label class="field"><span>Motivo</span><input name="reason" value="{{ old('reason','Uso operacional') }}"></label>
<label class="field"><span>Observação opcional</span><textarea name="observation">{{ old('observation') }}</textarea></label>
<button class="button primary large">Confirmar retirada</button>
</form>
</article>
@endcan

@can('chemical_stock.transfer')
<article class="panel-card">
<div class="panel-head"><div><span class="eyebrow">MOVIMENTAÇÃO INTERNA</span><h2>Transferir entre locais</h2><p>Origem e destino devem pertencer ao mesmo produto e usar a mesma unidade.</p></div></div>
<form method="POST" action="{{ route('chemical-stock.transfer.store') }}" class="stack-form">@csrf
<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
<label class="field"><span>Origem</span><select name="source_location_id" required><option value="">Selecione</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->product->name }} — {{ $location->name }}</option>@endforeach</select></label>
<label class="field"><span>Destino</span><select name="destination_location_id" required><option value="">Selecione</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->product->name }} — {{ $location->name }}</option>@endforeach</select></label>
<label class="field"><span>Quantidade</span><input type="number" inputmode="decimal" min="0.001" step="any" name="quantity" required></label>
<label class="field"><span>Motivo opcional</span><input name="reason"></label>
<button class="button primary large">Confirmar transferência</button>
</form>
</article>
@endcan

@can('chemical_stock.inventory')
<article class="panel-card">
<div class="panel-head"><div><span class="eyebrow">INVENTÁRIO</span><h2>Conferir produto ou local</h2><p>Esta conferência é complementar à contagem cega do turno e passa a ser a nova referência física.</p></div></div>
<form method="POST" action="{{ route('chemical-stock.inventory.store') }}" class="stack-form">@csrf
<input type="hidden" name="idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
<label class="field"><span>Produto e local</span><select name="chemical_storage_location_id" required><option value="">Selecione</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->product->name }} — {{ $location->name }}</option>@endforeach</select></label>
<label class="field"><span>Quantidade física encontrada</span><input type="number" inputmode="decimal" min="0" step="any" name="physical_quantity" required></label>
<label class="field"><span>Motivo da divergência, se houver</span><textarea name="reason"></textarea></label>
<label class="field"><span>Observação opcional</span><textarea name="observation"></textarea></label>
<button class="button primary large">Registrar inventário</button>
</form>
</article>
@endcan
</section>

@can('chemical_stock.issue')
<section class="panel-card">
<div class="panel-head"><div><span class="eyebrow">POLÍMERO NA UAP</span><h2>Sacos abertos em uso</h2><p>A retirada baixa 1 saco do estoque. As pesagens abaixo controlam os 25 kg disponibilizados para consumo.</p></div><b>{{ $openPackages->count() }} aberto(s)</b></div>
<div class="list">
@forelse($openPackages as $package)
<div class="list-row open-package-row"><div><b>{{ $package->product->name }} • saco #{{ $package->id }}</b><small>{{ number_format((float)$package->remaining_content,$package->contentUnit->decimal_places,',','.') }} {{ $package->contentUnit->symbol }} restantes de {{ number_format((float)$package->initial_content,$package->contentUnit->decimal_places,',','.') }} • {{ $package->usage_location }} • aberto por {{ $package->opener->name }} em {{ $package->opened_at->format('d/m H:i') }}</small></div>
<details><summary>Registrar pesagem</summary><form method="POST" action="{{ route('chemical-stock.open-packages.weigh',$package) }}" class="stack-form">@csrf<label class="field"><span>Peso restante (kg)</span><input type="number" name="remaining" min="0" max="{{ $package->remaining_content }}" step="0.001" required></label><label class="field"><span>Observação opcional</span><input name="observation"></label><button class="button primary">Salvar pesagem</button></form></details>
</div>
@empty<div class="empty-state"><b>Nenhum saco aberto</b><span>Ao retirar um saco de polímero, ele aparecerá aqui com 25 kg disponíveis.</span></div>
@endforelse
</div>
</section>
@endcan
</main></div>
@endsection