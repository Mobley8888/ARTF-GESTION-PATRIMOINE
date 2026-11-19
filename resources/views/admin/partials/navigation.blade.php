<nav class="d-grid gap-1" aria-label="Navigation principale">
    <a class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
        <i class="bi bi-grid-1x2"></i><span>Tableau de bord</span>
    </a>
    <a class="sidebar-link {{ request()->routeIs('assets.*') ? 'active' : '' }}" href="{{ route('assets.index') }}">
        <i class="bi bi-box-seam"></i><span>Biens patrimoniaux</span>
    </a>
    @can('manage-assets')
        <p class="sidebar-label mt-3 mb-1">Référentiels</p>
        <a class="sidebar-link {{ request()->routeIs('references.categories.*') ? 'active' : '' }}" href="{{ route('references.categories.index') }}">
            <i class="bi bi-tags"></i><span>Catégories</span>
        </a>
        <a class="sidebar-link {{ request()->routeIs('references.locations.*') ? 'active' : '' }}" href="{{ route('references.locations.index') }}">
            <i class="bi bi-geo-alt"></i><span>Sites et localisations</span>
        </a>
    @endcan
    <a class="sidebar-link {{ request()->routeIs('assignments.*') ? 'active' : '' }}" href="{{ route('assignments.index') }}">
        <i class="bi bi-arrow-left-right"></i><span>Affectations</span>
    </a>
    <a class="sidebar-link {{ request()->routeIs('inventories.*') ? 'active' : '' }}" href="{{ route('inventories.index') }}">
        <i class="bi bi-clipboard-check"></i><span>Inventaires</span>
    </a>
    <a class="sidebar-link {{ request()->routeIs('maintenance.*') ? 'active' : '' }}" href="{{ route('maintenance.index') }}">
        <i class="bi bi-tools"></i><span>Maintenance</span>
    </a>
    <a class="sidebar-link {{ request()->routeIs('stock.*') ? 'active' : '' }}" href="{{ route('stock.index') }}">
        <i class="bi bi-boxes"></i><span>Stock patrimonial</span>
    </a>
    @can('view-maintenance-costs')
        <a class="sidebar-link {{ request()->routeIs('maintenance.costs*') ? 'active' : '' }}" href="{{ route('maintenance.costs') }}">
            <i class="bi bi-cash-coin"></i><span>Coûts de maintenance</span>
        </a>
    @endcan
    <a class="sidebar-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.index') }}">
        <i class="bi bi-bar-chart"></i><span>Rapports</span>
    </a>
    @can('manage-users')
        <a class="sidebar-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
            <i class="bi bi-people"></i><span>Utilisateurs</span>
        </a>
    @endcan
</nav>
