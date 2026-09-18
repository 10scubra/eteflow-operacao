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
        <a class="nav-link {{ $is('dosing.*') ? 'active' : '' }}" href="{{ route('dosing.index') }}"><span>◉</span> Dosagem</a>
        @can('indicators.view')<a class="nav-link {{ $is('indicators.*') ? 'active' : '' }}" href="{{ route('indicators.index') }}"><span>▥</span> Indicadores</a>@endcan
        @can('laboratory.view')<a class="nav-link {{ $is('laboratory.*') ? 'active' : '' }}" href="{{ route('laboratory.index') }}"><span>⌬</span> Análises</a>@endcan
        @if(app(\App\Services\PermissionService::class)->allows(auth()->user(), 'chemical_stock.view_balance'))
            <a class="nav-link {{ $is('chemical-stock.*') ? 'active' : '' }}" href="{{ route('chemical-stock.dashboard') }}"><span>▣</span> Estoque químico</a>
        @elseif(app(\App\Services\PermissionService::class)->allows(auth()->user(), 'chemical_stock.issue'))
            <a class="nav-link {{ $is('chemical-stock.*') ? 'active' : '' }}" href="{{ route('chemical-stock.operations') }}"><span>▣</span> Estoque químico</a>
        @elseif(app(\App\Services\PermissionService::class)->allows(auth()->user(), 'chemical_stock.count'))
            <a class="nav-link {{ $is('chemical-stock.*') ? 'active' : '' }}" href="{{ route('chemical-stock.count.form') }}"><span>▣</span> Estoque químico</a>
        @endif
        <a class="nav-link {{ $is('actions.*') ? 'active' : '' }}" href="{{ route('actions.show') }}"><span>✓</span> Ações <em>{{ $pendingActions ?? '' }}</em></a>
        <a class="nav-link {{ $is('aspersion*') ? 'active' : '' }}" href="{{ route('aspersion') }}"><span>≈</span> Aspersão</a>
        <a class="nav-link {{ $is('occurrences*', 'handover*', 'more') ? 'active' : '' }}" href="{{ route('more') }}"><span>•••</span> Mais</a>
        @can('employees.view')
            <span class="nav-section-label">ADMINISTRAÇÃO</span>
            <a class="nav-link {{ $is('admin.employees.*') ? 'active' : '' }}" href="{{ route('admin.employees.index') }}"><span>♙</span> Colaboradores</a>
        @endcan
        @can('administration.manage_permissions')
            <a class="nav-link {{ $is('admin.roles.*') ? 'active' : '' }}" href="{{ route('admin.roles.index') }}"><span>⚿</span> Perfis e permissões</a>
        @endcan
        @can('readings.configure')
            <a class="nav-link {{ $is('admin.reading-templates.*') ? 'active' : '' }}" href="{{ route('admin.reading-templates.index') }}"><span>▤</span> Construtor de leituras</a>
        @endcan
        @can('indicators.configure')<a class="nav-link {{ $is('admin.indicators.*') ? 'active' : '' }}" href="{{ route('admin.indicators.index') }}"><span>⚙</span> Configurar indicadores</a>@endcan
        @can('chemical_stock.configure')<a class="nav-link {{ $is('admin.chemical-stock.*') ? 'active' : '' }}" href="{{ route('admin.chemical-stock.index') }}"><span>⚙</span> Configurar estoque</a>@endcan
        @if(auth()->user()->role === 'master')<a class="nav-link {{ $is('admin.dosing.*') ? 'active' : '' }}" href="{{ route('admin.dosing.configure') }}"><span>⚙</span> Assistente de dosagem</a>@endif
        @can('shifts.manage_team')
            <a class="nav-link {{ $is('team.*') ? 'active' : '' }}" href="{{ route('team.index') }}"><span>♟</span> Turno / Equipe</a>
        @endcan
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
    <a class="{{ $is('more', 'occurrences*', 'handover*', 'team.*', 'admin.*') ? 'active' : '' }}" href="{{ route('more') }}"><span>•••</span>Mais</a>
</nav>
