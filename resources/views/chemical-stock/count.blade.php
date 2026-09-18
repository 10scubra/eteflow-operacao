@extends('layouts.app')
@section('title','Conferência de químicos • ETEFlow')
@section('page','chemical-stock-count')
@section('content')
<div class="app-shell">@include('operation.navigation')<main class="workspace"><header class="page-header"><div><span class="eyebrow">MEU TURNO</span><h1>Conferência de químicos</h1><p>Faça a contagem física sem referência de valores anteriores.</p></div></header>
@if(session('success'))<div class="success-alert">✓ {{ session('success') }}</div>@endif @if($errors->any())<div class="form-alert">{{ $errors->first() }}</div>@endif
@if($count)<section class="operator-hero"><div><span class="eyebrow accent">CONCLUÍDA</span><h2>Conferência registrada</h2><p>{{ $count->counted_at->format('d/m/Y • H:i') }}</p></div></section>
@else<form method="POST" action="{{ route('chemical-stock.count.store') }}" class="stack-form">@csrf<section class="module-grid">@foreach($locations as $location)<article class="panel-card"><span class="eyebrow">{{ $location->product->name }}</span><h2>{{ $location->name }}</h2><label class="field"><span>Quantidade encontrada</span><div class="input-unit"><input name="quantities[{{ $location->id }}]" type="number" inputmode="decimal" min="0" step="{{ $location->product->decimal_places ? '0.'.str_repeat('0',$location->product->decimal_places-1).'1' : '1' }}" required value="{{ old('quantities.'.$location->id) }}"><b>{{ $location->unit->symbol }}</b></div></label></article>@endforeach</section><label class="field"><span>Observação opcional</span><textarea name="observation">{{ old('observation') }}</textarea></label><button class="button primary large">Confirmar contagem</button></form>@endif
@can('chemical_stock.receive')<p><a class="button secondary" href="{{ route('chemical-stock.receipt.form') }}">+ Registrar entrada de produto</a></p>@endcan</main></div>
@endsection
