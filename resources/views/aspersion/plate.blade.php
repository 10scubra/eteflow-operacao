@extends('layouts.app')

@section('title', 'QR Code • '.$aspersionPoint->name)
@section('page', 'aspersion-plate')

@section('content')
<main class="plate-shell">
    <section class="qr-plate">
        <div class="qr-plate-brand"><img src="{{ asset('assets/icon.svg') }}" alt="" width="48" height="48"><span>ETEFlow</span></div>
        <span class="eyebrow">CONTROLE DE ASPERSÃO</span>
        <h1>{{ $aspersionPoint->name }}</h1>
        <p>{{ $aspersionPoint->location ?: 'Painel de acionamento' }}</p>
        <img class="qr-image" src="{{ $qrCodeDataUri }}" alt="QR Code para registrar a aspersão em {{ $aspersionPoint->name }}">
        <strong>Escaneie ao ligar e ao desligar</strong>
        <small>Informe totalizador, vazão e canhões ativos.</small>
        <code>{{ $publicUrl }}</code>
    </section>
    <div class="plate-actions"><button class="button primary large" onclick="window.print()">Imprimir plaquinha</button><a class="button secondary" href="{{ route('aspersion') }}">Voltar ao controle</a></div>
</main>
@endsection
