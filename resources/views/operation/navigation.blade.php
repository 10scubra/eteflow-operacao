@php
    $is = fn (...$names) => request()->routeIs(...$names);
@endphp
<aside class="sidebar">
    <a class="brand" href="{{ route('operation.home') }}"><span class="brand-mark small">EF</span><span><b>ETEFlow</b><small>{{ auth()->user()->role === 'master' ? 'Gestão Master' : 'Operação' }}</small></span></a>
    <nav>
        <a class="nav-link {{ $is('operation.home') ? 'active' : '' }}" href="{{ route('operation.home') }}"><span>⌂</span> Operação</a>
        @if(auth()->user()->role === 'master')
            <a class="nav-link {{ $is('master') ? 'active' : '' }}" href="{{ route('master') }}"><span>◫</span> Dashboard Master</a>
        @endif
        <a class="nav-link {{ $is('readings') ? 'active' : '' }}" href="{{ route('readings') }}"><span>▦</span> Leituras</a>
        <a class="nav-link {{ $is('actions.*') ? 'active' : '' }}" href="{{ route('actions.show') }}"><span>✓</span> Ações <em>{{ $pendingActions ?? '' }}</em></a>
        <a class="nav-link {{ $is('aspersion*') ? 'active' : '' }}" href="{{ route('aspersion') }}"><span>≈</span> Aspersão</a>
        <a class="nav-link {{ $is('occurrences*', 'handover*', 'team.*', 'more') ? 'active' : '' }}" href="{{ route('more') }}"><span>•••</span> Mais</a>
    </nav>
    <div class="sidebar-user">
        <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 2)) }}</span>
        <div><b>{{ auth()->user()->name }}</b><small>{{ auth()->user()->username }}</small></div>
        <form method="POST" action="{{ route('logout') }}">@csrf<button aria-label="Sair">↪</button></form>
    </div>
</aside>
<nav class="mobile-nav">
    <a class="{{ $is('operation.home') ? 'active' : '' }}" href="{{ route('operation.home') }}"><span>⌂</span>Operação</a>
    <a class="{{ $is('readings') ? 'active' : '' }}" href="{{ route('readings') }}"><span>▦</span>Leituras</a>
    <a class="{{ $is('actions.*') ? 'active' : '' }}" href="{{ route('actions.show') }}"><span>✓</span>Ações</a>
    <a class="{{ $is('aspersion*') ? 'active' : '' }}" href="{{ route('aspersion') }}"><span>≈</span>Aspersão</a>
    <a class="{{ $is('more', 'occurrences*', 'handover*', 'team.*') ? 'active' : '' }}" href="{{ route('more') }}"><span>•••</span>Mais</a>
</nav>
