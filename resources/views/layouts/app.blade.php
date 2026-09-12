<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#07111f">
    <meta name="application-name" content="ETEFlow">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('assets/icon.svg') }}" type="image/svg+xml">
    <title>@yield('title', 'ETEFlow Operação')</title>
    <link rel="stylesheet" href="{{ asset('assets/app.css') }}">
    <script type="module" src="{{ asset('assets/app.js') }}" defer></script>
</head>
<body data-page="@yield('page')" data-server-time="{{ now()->toIso8601String() }}">
    @yield('content')
    <div id="toast" class="toast" role="status" aria-live="polite"></div>
</body>
</html>
