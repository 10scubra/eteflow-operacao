@extends('layouts.app')
@section('title','Estoque de químicos • ETEFlow')
@section('page','chemical-stock-dashboard')
@section('content')
@php
$statusLabels=['UNKNOWN'=>'Sem contagem','NORMAL'=>'Normal','LOW'=>'Estoque baixo','CRITICAL'=>'Estoque crítico','EMPTY'=>'Sem estoque'];
$statusColors=['UNKNOWN'=>'#64748b','NORMAL'=>'#31d39a','LOW'=>'#f6bd54','CRITICAL'=>'#ff7272','EMPTY'=>'#c23b55'];
$statusCounts=collect(array_keys($statusLabels))->mapWithKeys(fn($status)=>[$status=>$products->where('status',$status)->count()]);
$palette=['#31d39a','#3ca6ff','#f6bd54','#c58cff','#ff7272'];
$locationCount=$products->sum(fn($product)=>$product['locations']->count());
@endphp
<div class="app-shell">
@include('operation.navigation')
<main class="workspace stock-bi">
<header class="page-header stock-bi-header">
    <div><span class="eyebrow">CONTROLE GERENCIAL</span><h1>Estoque de produtos químicos</h1><p>Saldo, consumo estimado e pontos de atenção para decisão operacional.</p></div>
    <div class="card-actions"><a class="button secondary" href="{{ route('chemical-stock.operations') }}">Movimentações</a>@can('chemical_stock.view_analytics')<a class="button secondary" href="{{ route('chemical-stock.history') }}">Histórico</a>@endcan<a class="button primary" href="{{ route('chemical-stock.receipt.form') }}">+ Registrar entrada</a></div>
</header>
@if($analytics['is_demo'])<div class="demo-banner"><b>Base demonstrativa</b><span>Os dados fictícios estão identificados no histórico e servem somente para validar o painel.</span></div>@endif

<details class="dashboard-filters" open>
<summary><span><b>Filtros do painel</b><small>Refine a análise sem alterar os registros</small></span><i>⌄</i></summary>
<form method="GET" class="bi-filter-grid" data-dashboard-filter-form>
    <label><span>Período</span><select name="period" data-auto-filter>@foreach([7,14,30,90] as $option)<option value="{{ $option }}" @selected($period===$option)>Últimos {{ $option }} dias</option>@endforeach</select></label>
    <label><span>Produto</span><select name="product" data-auto-filter><option value="">Todos os produtos</option>@foreach($productOptions as $option)<option value="{{ $option->id }}" @selected($filters['product']===$option->id)>{{ $option->name }}</option>@endforeach</select></label>
    <label><span>Local</span><select name="location" data-auto-filter><option value="">Todos os locais</option>@foreach($locationOptions as $option)<option value="{{ $option->id }}" @selected($filters['location']===$option->id)>{{ $option->product->name }} — {{ $option->name }}</option>@endforeach</select></label>
    <label><span>Situação</span><select name="status" data-auto-filter><option value="">Todas as situações</option>@foreach($statusLabels as $key=>$label)<option value="{{ $key }}" @selected($filters['status']===$key)>{{ $label }}</option>@endforeach</select></label>
    <button class="button primary" type="submit">Aplicar filtros</button>
    <a class="button ghost" href="{{ route('chemical-stock.dashboard') }}">Limpar</a>
</form>
</details>

<section class="bi-kpis">
    <article><span>Produtos monitorados</span><strong>{{ $products->count() }}</strong><small>{{ $locationCount }} {{ $locationCount===1?'local':'locais' }} na seleção</small></article>
    <article class="{{ $statusCounts['LOW']+$statusCounts['CRITICAL']+$statusCounts['EMPTY'] ? 'attention' : '' }}"><span>Produtos em atenção</span><strong>{{ $statusCounts['LOW']+$statusCounts['CRITICAL']+$statusCounts['EMPTY'] }}</strong><small>{{ $alerts->count() }} alerta(s) aberto(s)</small></article>
    <article><span>Conferências</span><strong>{{ $analytics['count_count'] }}</strong><small>nos últimos {{ $period }} dias</small></article>
    <article><span>Recebimentos</span><strong>{{ $analytics['receipt_count'] }}</strong><small>entradas confirmadas</small></article>
</section>

<section class="status-ribbon" aria-label="Resumo de situação">
@foreach($statusLabels as $status=>$label)<a class="status-filter-chip {{ $filters['status']===$status?'active':'' }}" href="{{ route('chemical-stock.dashboard',array_merge(request()->except('page'),['status'=>$status])) }}"><i style="background:{{ $statusColors[$status] }}"></i><span>{{ $label }}</span><b>{{ $statusCounts[$status] }}</b></a>@endforeach
<span class="last-update">Atualizado em <b>{{ $analytics['last_update'] ? \Carbon\Carbon::parse($analytics['last_update'])->format('d/m/Y • H:i') : 'sem dados' }}</b></span>
</section>

<section class="management-chart-grid">
<article class="panel-card chart-card primary-chart">
    <div class="panel-head"><div><span class="eyebrow">EVOLUÇÃO DO ESTOQUE</span><h2>Nível ao longo do período</h2><p>Percentual da capacidade registrado nas contagens e movimentações</p></div><div class="chart-key"><span><i class="receipt-key"></i>Entrada</span></div></div>
    @if(count($analytics['series']))
    @php $chartW=960;$chartH=310;$chartL=48;$chartR=20;$chartT=20;$chartB=42;$plotW=$chartW-$chartL-$chartR;$plotH=$chartH-$chartT-$chartB; @endphp
    <div class="line-chart" data-interactive-stock-chart><script type="application/json" class="stock-chart-data">{!! json_encode(['labels'=>$analytics['labels'],'series'=>$analytics['series'],'receipts'=>$analytics['receipts']], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script><div class="chart-hover-tooltip" hidden></div><svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" role="img" aria-label="Evolução percentual do estoque">
    @foreach([0,25,50,75,100] as $tick) @php $gy=$chartT+$plotH-($tick/100*$plotH); @endphp <line class="grid-line" x1="{{ $chartL }}" y1="{{ $gy }}" x2="{{ $chartW-$chartR }}" y2="{{ $gy }}"/><text class="axis-text" x="{{ $chartL-8 }}" y="{{ $gy+4 }}" text-anchor="end">{{ $tick }}%</text> @endforeach
    @foreach(['low'=>'low-line','critical'=>'critical-line'] as $threshold=>$class) @if($analytics['thresholds'][$threshold]!==null) @php $ty=$chartT+$plotH-($analytics['thresholds'][$threshold]/100*$plotH); @endphp <line class="threshold-line {{ $class }}" x1="{{ $chartL }}" y1="{{ $ty }}" x2="{{ $chartW-$chartR }}" y2="{{ $ty }}"><title>Limite {{ $threshold==='low'?'baixo':'crítico' }}: {{ $analytics['thresholds'][$threshold] }}%</title></line>@endif @endforeach
    @foreach($analytics['labels'] as $index=>$label) @php $px=$chartL+($index/max(1,count($analytics['labels'])-1)*$plotW); @endphp @if($index%max(1,(int)ceil(count($analytics['labels'])/7))===0)<text class="axis-text" x="{{ $px }}" y="{{ $chartH-9 }}" text-anchor="middle">{{ $label }}</text>@endif @if(($analytics['receipts'][$index]??0)>0)<rect class="receipt-marker" x="{{ $px-3 }}" y="{{ $chartT+$plotH-12 }}" width="6" height="12"><title>{{ $analytics['receipts'][$index] }} entrada(s) em {{ $label }}</title></rect>@endif @endforeach
    @foreach($analytics['series'] as $seriesIndex=>$series) @php $points=collect($series['values'])->map(fn($value,$index)=>$value===null?null:round($chartL+($index/max(1,count($series['values'])-1)*$plotW),1).','.round($chartT+$plotH-(max(0,min(100,$value))/100*$plotH),1))->filter()->implode(' '); @endphp @if($points)<polyline class="data-line chart-series series-{{ $seriesIndex }}" data-series="{{ $seriesIndex }}" points="{{ $points }}" style="stroke:{{ $palette[$seriesIndex%count($palette)] }}"/>@foreach($series['values'] as $index=>$value) @if($value!==null)<circle class="data-point chart-series series-{{ $seriesIndex }}" data-series="{{ $seriesIndex }}" data-point-index="{{ $index }}" cx="{{ $chartL+($index/max(1,count($series['values'])-1)*$plotW) }}" cy="{{ $chartT+$plotH-(max(0,min(100,$value))/100*$plotH) }}" r="3" style="fill:{{ $palette[$seriesIndex%count($palette)] }}"><title>{{ $series['name'] }} • {{ $analytics['labels'][$index] }}: {{ number_format($value,1,',','.') }}%</title></circle>@endif @endforeach @endif @endforeach
    </svg></div>
    <div class="series-legend">@foreach($analytics['series'] as $index=>$series)<button type="button" class="series-toggle active" data-series-toggle="{{ $index }}" aria-pressed="true"><i style="background:{{ $palette[$index%count($palette)] }}"></i>{{ $series['name'] }}</button>@endforeach</div>
    @else <div class="empty-state"><b>Sem dados para o gráfico</b><span>A seleção atual não possui snapshots com capacidade configurada.</span></div> @endif
</article>

<article class="panel-card consumption-card">
    <div class="panel-head"><div><span class="eyebrow">CONSUMO ESTIMADO</span><h2>Variação entre contagens</h2><p>Comparação percentual sem misturar unidades</p></div></div>
    <div class="consumption-list">@forelse($analytics['consumption'] as $index=>$row)<div class="consumption-row interactive-row" role="link" tabindex="0" data-filter-url="{{ route('chemical-stock.dashboard',array_merge(request()->except(['page','product','location']),['product'=>$row['product']->id])) }}"><div><b>{{ $row['product']->name }}</b><span>{{ number_format($row['amount'],$row['product']->decimal_places,',','.') }} {{ $row['product']->unit->symbol }}</span></div><strong>{{ $row['percentage']===null?'—':number_format($row['percentage'],1,',','.').'%' }}</strong><div class="consumption-track"><i style="width:{{ min(100,max(0,$row['percentage']??0)) }}%;background:{{ $palette[$index%count($palette)] }}"></i></div>@if($row['divergence_gain']>0)<small class="positive-divergence">+ {{ number_format($row['divergence_gain'],$row['product']->decimal_places,',','.') }} {{ $row['product']->unit->symbol }} de divergência positiva</small>@endif</div>@empty<div class="empty-state">Sem consumo calculável no período.</div>@endforelse</div>
</article>
</section>

<section class="management-bottom-grid">
<article class="panel-card stock-table-card">
<div class="panel-head"><div><span class="eyebrow">POSIÇÃO ATUAL</span><h2>Produtos e locais</h2><p>Saldo, capacidade, consumo e tendência em uma única visão</p></div></div>
<div class="management-table-wrap"><table class="management-table"><thead><tr><th>Produto / local</th><th>Saldo atual</th><th>Capacidade</th><th>Nível</th><th>Consumo estimado</th><th>Última contagem</th><th>Situação</th><th>Tendência</th></tr></thead><tbody>
@forelse($analytics['table'] as $row) @php $location=$row['location'];$unit=$location->product->unit->symbol;$trend=$row['trend'];$tw=110;$th=30;$min=count($trend)?min($trend):0;$max=count($trend)?max($trend):1;$range=max(1,$max-$min);$trendPoints=collect($trend)->map(fn($value,$index)=>round($index/max(1,count($trend)-1)*$tw,1).','.round($th-(($value-$min)/$range*$th),1))->implode(' '); @endphp
<tr class="interactive-row" data-filter-url="{{ route('chemical-stock.dashboard',array_merge(request()->except(['page','product','location']),['product'=>$location->chemical_product_id,'location'=>$location->id])) }}"><td data-label="Produto / local"><b>{{ $location->product->name }}</b><small>{{ $location->name }}</small></td><td data-label="Saldo atual"><b>{{ $row['balance']===null?'—':number_format($row['balance'],$location->product->decimal_places,',','.') }}</b> {{ $row['balance']===null?'':$unit }}</td><td data-label="Capacidade">{{ $location->capacity===null?'Não configurada':number_format((float)$location->capacity,$location->product->decimal_places,',','.').' '.$unit }}</td><td data-label="Nível">{{ $row['percentage']===null?'—':number_format($row['percentage'],1,',','.').'%' }}</td><td data-label="Consumo estimado">{{ $row['consumption']===null?'Sem intervalo':number_format($row['consumption'],$location->product->decimal_places,',','.').' '.$unit }}@if(($row['divergence_gain']??0)>0)<small class="positive-divergence">Divergência +{{ number_format($row['divergence_gain'],$location->product->decimal_places,',','.') }} {{ $unit }}</small>@endif</td><td data-label="Última contagem">{{ $row['last_count_at']?->format('d/m/Y H:i')??'Sem contagem' }}</td><td data-label="Situação"><span class="status-badge {{ strtolower($row['status']) }}">{{ $statusLabels[$row['status']] }}</span></td><td data-label="Tendência">@if(count($trend)>1)<svg class="sparkline" viewBox="0 0 {{ $tw }} {{ $th }}" aria-label="Tendência"><polyline points="{{ $trendPoints }}"/></svg>@else<span class="muted">—</span>@endif</td></tr>
@empty<tr><td colspan="8"><div class="empty-state"><b>Nenhum resultado</b><span>Ajuste ou limpe os filtros do painel.</span></div></td></tr>@endforelse
</tbody></table></div>
</article>
<article class="panel-card alerts-card"><div class="panel-head"><div><span class="eyebrow">PRIORIDADES</span><h2>Alertas abertos</h2></div><b class="alert-total">{{ $alerts->count() }}</b></div>@forelse($alerts as $alert)<div class="alert-line"><i class="{{ strtolower($alert->severity) }}"></i><div><b>{{ $alert->product->name }}</b><span>{{ $alert->location->name }} • {{ $statusLabels[$alert->severity] }}</span><small>Saldo no alerta: {{ $alert->balance_snapshot===null?'—':number_format((float)$alert->balance_snapshot,$alert->product->decimal_places,',','.').' '.$alert->product->unit->symbol }}</small></div><time>{{ $alert->opened_at->format('d/m H:i') }}</time></div>@empty<div class="empty-state"><b>Nenhum alerta aberto</b><span>Os produtos filtrados não exigem atenção.</span></div>@endforelse</article>
</section>
</main></div>
@endsection