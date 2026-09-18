@extends('layouts.app')

@section('title', 'Dashboard Master • ETEFlow')
@section('page', 'master')

@section('content')
<div class="app-shell">
    @include('operation.navigation')

    <main class="workspace master-workspace">
        <header class="page-header">
            <div>
                <span class="eyebrow">ACOMPANHAMENTO EM TEMPO REAL</span>
                <h1>Visão rápida da operação</h1>
                <p>Atualização automática a cada 5 segundos</p>
            </div>
            <div class="server-clock"><span>HORÁRIO DO SERVIDOR</span><strong id="live-clock">--:--:--</strong><small id="live-date"></small></div>
        </header>

        <section id="master-loading" class="loading-card">Sincronizando a operação…</section>

        <div id="master-content" hidden>
            <section class="master-hero">
                <article class="shift-card">
                    <div><span class="eyebrow accent">TURNO ATUAL</span><h2 id="shift-title">Identificando turno…</h2><p id="shift-team"></p></div>
                    <div class="shift-window"><strong id="shift-window"></strong><span id="shift-state"></span></div>
                </article>
                <article class="current-card">
                    <span id="round-kicker" class="eyebrow">RODADA ATUAL</span>
                    <div><strong id="current-round-time">--:--</strong><span id="current-round-relative"></span></div>
                    <div class="progress"><i id="master-progress-bar"></i></div>
                    <small id="master-progress-copy"></small>
                </article>
            </section>

            <section class="kpi-grid">
                <article class="kpi"><span class="kpi-icon blue">▦</span><label>Leituras concluídas</label><strong id="kpi-completed">0</strong><small id="kpi-completed-copy"></small></article>
                <article class="kpi"><span class="kpi-icon amber">!</span><label>Rodadas atrasadas</label><strong id="kpi-overdue">0</strong><small>Aguardando conclusão</small></article>
                <article class="kpi"><span class="kpi-icon red">↗</span><label>Fora da faixa</label><strong id="kpi-alerts">0</strong><small>Valores preservados</small></article>
                <article class="kpi"><span class="kpi-icon green">●</span><label>Equipe no turno</label><strong id="kpi-team">0</strong><small>Operadores vinculados</small></article>
            </section>

            <section class="master-grid">
                @can('chemical_stock.view_balance')<article class="panel-card wide"><div class="panel-head"><div><h2>Estoque de químicos</h2><p>Resumo gerencial sem ampliar o painel</p></div><a href="{{ route('chemical-stock.dashboard') }}">Abrir estoque →</a></div><div id="chemical-stock-summary" class="kpi-grid"></div></article>@endcan
                <article class="panel-card wide">
                    <div class="panel-head"><div><h2>Rodadas do turno</h2><p>Andamento por horário e por bloco</p></div><a href="{{ route('readings') }}">Abrir leituras →</a></div>
                    <div id="master-rounds" class="master-rounds"></div>
                </article>
                <div class="panel-stack">
                    <article class="panel-card">
                        <div class="panel-head"><div><h2>Alertas de faixa</h2><p>Parâmetros que precisam de atenção</p></div></div>
                        <div id="alerts-list" class="list"></div>
                    </article>
                    <article class="panel-card">
                        <div class="panel-head"><div><h2>Atividade recente</h2><p>Autoria e horário preservados</p></div></div>
                        <div id="activities-list" class="list"></div>
                    </article>
                </div>
            </section>
        </div>
    </main>

</div>
@endsection
