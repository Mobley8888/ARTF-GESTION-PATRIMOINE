@extends('admin.layout')

@section('title', 'Tableau de bord')

@section('content')
    <div data-dashboard-role="{{ $role->value }}">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
            <div>
                <p class="text-primary text-uppercase fw-semibold small mb-1">{{ $role->label() }}</p>
                <h1 class="h3 mb-1">Tableau de bord</h1>
                <p class="text-secondary mb-0">Indicateurs calculés à partir des données patrimoniales enregistrées.</p>
            </div>
            @can('manage-assets')
                <a class="btn btn-primary flex-shrink-0" href="{{ route('assets.create') }}">
                    <i class="bi bi-plus-lg me-1"></i> Enregistrer un bien
                </a>
            @endcan
        </div>

        @if (session('status'))
            <div class="alert alert-success alert-dismissible fade show" role="status">
                {{ session('status') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
            </div>
        @endif

        @php
            $metricCards = [
                ['key' => 'assets_total', 'label' => $role->value === 'viewer' ? 'Biens consultables' : 'Biens enregistrés', 'icon' => 'bi-box-seam'],
                ['key' => 'assets_active', 'label' => 'Biens en service', 'icon' => 'bi-check-circle'],
                ['key' => 'assets_inactive', 'label' => 'Biens non en service', 'icon' => 'bi-pause-circle'],
            ];

            if ($role->value !== 'viewer') {
                $metricCards[] = ['key' => 'assets_recent', 'label' => 'Enregistrés depuis 30 jours', 'icon' => 'bi-calendar2-plus'];
            }

            if ($role->value === 'asset_manager') {
                $metricCards[] = ['key' => 'assets_available', 'label' => 'Biens disponibles', 'icon' => 'bi-box2-heart'];
                $metricCards[] = ['key' => 'assets_assigned', 'label' => 'Biens affectés', 'icon' => 'bi-person-check'];
            }

            if (in_array($role->value, ['admin', 'super_admin'], true)) {
                $metricCards[] = ['key' => 'locations_active', 'label' => 'Localisations actives', 'icon' => 'bi-geo-alt'];
                $metricCards[] = ['key' => 'locations_inactive', 'label' => 'Localisations désactivées', 'icon' => 'bi-geo-alt-fill'];
            }

            if ($role->value === 'super_admin') {
                $metricCards = array_merge($metricCards, [
                    ['key' => 'categories', 'label' => 'Catégories principales', 'icon' => 'bi-tags'],
                    ['key' => 'subcategories', 'label' => 'Sous-catégories', 'icon' => 'bi-tag'],
                    ['key' => 'sites', 'label' => 'Sites', 'icon' => 'bi-buildings'],
                    ['key' => 'buildings', 'label' => 'Bâtiments', 'icon' => 'bi-building'],
                    ['key' => 'locals', 'label' => 'Locaux', 'icon' => 'bi-door-open'],
                    ['key' => 'users_total', 'label' => 'Utilisateurs', 'icon' => 'bi-people'],
                    ['key' => 'users_active', 'label' => 'Comptes actifs', 'icon' => 'bi-person-check'],
                    ['key' => 'users_inactive', 'label' => 'Comptes désactivés', 'icon' => 'bi-person-x'],
                ]);
            }

            $allMetrics = array_merge($metrics, $assignmentMetrics ?? [], $referenceMetrics ?? [], $locationMetrics ?? [], $userMetrics ?? []);
        @endphp

        <div class="row g-3 mb-4">
            @foreach ($metricCards as $card)
                <div class="col-12 col-sm-6 col-xl-3">
                    <section class="card metric-card h-100">
                        <div class="card-body d-flex align-items-center justify-content-between">
                            <div>
                                <p class="text-secondary small mb-2">{{ $card['label'] }}</p>
                                <p class="h3 fw-semibold mb-0" data-dashboard-metric="{{ $card['key'] }}">{{ number_format($allMetrics[$card['key']] ?? 0, 0, ',', ' ') }}</p>
                            </div>
                            <span class="metric-icon"><i class="bi {{ $card['icon'] }}"></i></span>
                        </div>
                    </section>
                </div>
            @endforeach
        </div>

        <section class="card content-card mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2 mb-3">
                    <div>
                        <h2 class="h5 mb-1">Filtres du tableau de bord</h2>
                        <p class="small text-secondary mb-0">Les indicateurs et graphiques patrimoniaux suivent les critères sélectionnés.</p>
                    </div>
                    <a class="btn btn-sm btn-outline-secondary align-self-start align-self-lg-center" href="{{ route('dashboard') }}">Réinitialiser les filtres</a>
                </div>
                <form method="GET" action="{{ route('dashboard') }}" data-category-cascade>
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-sm-6 col-xl-2">
                            <label class="form-label" for="from">Du</label>
                            <input class="form-control" id="from" name="from" type="date" value="{{ $filters['from'] ?? '' }}">
                        </div>
                        <div class="col-12 col-sm-6 col-xl-2">
                            <label class="form-label" for="to">Au</label>
                            <input class="form-control" id="to" name="to" type="date" value="{{ $filters['to'] ?? '' }}">
                        </div>
                        <div class="col-12 col-sm-6 col-xl-2">
                            <label class="form-label" for="site_id">Site</label>
                            <select class="form-select" id="site_id" name="site_id">
                                <option value="">Tous les sites</option>
                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}" @selected(($filters['site_id'] ?? '') == $site->id)>
                                        {{ $site->name }}{{ $site->is_active ? '' : ' (désactivé)' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-2">
                            <label class="form-label" for="category_id">Catégorie principale</label>
                            <select class="form-select" id="category_id" name="category_id" data-category-parent>
                                <option value="">Toutes</option>
                                @foreach ($categoryRoots as $category)
                                    <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-2">
                            <label class="form-label" for="subcategory_id">Sous-catégorie</label>
                            <select class="form-select" id="subcategory_id" name="subcategory_id" data-category-child @disabled(empty($filters['category_id']))>
                                <option value="">Toutes</option>
                                @foreach ($subcategories as $subcategory)
                                    <option value="{{ $subcategory->id }}" data-parent-category="{{ $subcategory->parent_id }}" @selected(($filters['subcategory_id'] ?? '') == $subcategory->id)>{{ $subcategory->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-2">
                            <label class="form-label" for="status">Statut du bien</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">Tous</option>
                                @foreach ($statusLabels as $value => $label)
                                    <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-funnel me-1"></i> Appliquer les filtres</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <section class="card content-card mb-4">
            <div class="card-body">
                <h2 class="h5 mb-1">Recherche rapide des biens</h2>
                <p class="small text-secondary mb-3">Combinez une référence, une désignation ou un numéro de série avec les critères disponibles.</p>
                <form method="GET" action="{{ route('assets.index') }}">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-6 col-xl-3">
                            <label class="form-label" for="dashboard-search-q">Référence, désignation ou numéro de série</label>
                            <input class="form-control" id="dashboard-search-q" name="q" type="search" maxlength="100">
                        </div>
                        <div class="col-12 col-sm-6 col-xl-2">
                            <label class="form-label" for="dashboard-search-site">Site</label>
                            <select class="form-select" id="dashboard-search-site" name="site_id">
                                <option value="">Tous</option>
                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}">{{ $site->name }}{{ $site->is_active ? '' : ' (désactivé)' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-3">
                            <label class="form-label" for="dashboard-search-location">Localisation</label>
                            <select class="form-select" id="dashboard-search-location" name="location_id">
                                <option value="">Toutes</option>
                                @foreach ($searchLocations as $location)
                                    @php($locationLabel = collect([$location->parent?->parent?->name, $location->parent?->name, $location->name])->filter()->implode(' / '))
                                    <option value="{{ $location->id }}">{{ $locationLabel }}{{ $location->is_active ? '' : ' (désactivée)' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-2">
                            <label class="form-label" for="dashboard-search-category">Catégorie</label>
                            <select class="form-select" id="dashboard-search-category" name="category_id">
                                <option value="">Toutes</option>
                                @foreach ($categoryRoots as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @foreach ($category->children as $subcategory)
                                        <option value="{{ $subcategory->id }}">{{ $category->name }} / {{ $subcategory->name }}</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-sm-6 col-xl-2">
                            <label class="form-label" for="dashboard-search-status">Statut</label>
                            <select class="form-select" id="dashboard-search-status" name="status">
                                <option value="">Tous</option>
                                @foreach ($statusLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-2">
                            <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i> Rechercher</button>
                            <a class="btn btn-outline-secondary" href="{{ route('assets.index') }}">Recherche avancée</a>
                        </div>
                    </div>
                </form>
            </div>
        </section>

        <div class="row g-3 mb-4">
            @include('admin.partials.dashboard-chart', [
                'title' => 'Biens par catégorie',
                'rows' => $categoriesChart,
                'filterKey' => 'category_id',
                'color' => 'primary',
                'columnClass' => 'col-12 col-xl-6',
            ])
            @include('admin.partials.dashboard-chart', [
                'title' => 'Biens par site',
                'rows' => $sitesChart,
                'filterKey' => 'site_id',
                'color' => 'success',
                'columnClass' => 'col-12 col-xl-6',
            ])
            @include('admin.partials.dashboard-chart', [
                'title' => 'Biens par localisation',
                'rows' => $locationsChart,
                'filterKey' => 'location_id',
                'color' => 'info',
                'columnClass' => 'col-12 col-xl-6',
            ])
            @include('admin.partials.dashboard-chart', [
                'title' => 'Biens par statut',
                'rows' => $statusesChart,
                'filterKey' => 'status',
                'color' => 'warning',
                'columnClass' => 'col-12 col-xl-6',
            ])
            @if ($role->value === 'super_admin')
                @include('admin.partials.dashboard-chart', [
                    'title' => 'Biens par état',
                    'rows' => $conditionsChart,
                    'filterKey' => 'condition',
                    'color' => 'secondary',
                    'columnClass' => 'col-12 col-xl-6',
                ])
            @endif
        </div>

        @if (isset($monthlyMetrics))
            @php($monthlyMax = max(1, collect($monthlyMetrics)->max(fn (array $month) => max($month['assets'], $month['assignments'], $month['movements']))))
            <section class="card content-card mb-4">
                <div class="card-body p-4">
                    <h2 class="h5 mb-1">Évolution mensuelle</h2>
                    <p class="small text-secondary mb-4">Enregistrements, affectations et mouvements consignés dans les historiques.</p>
                    @forelse ($monthlyMetrics as $month)
                        <div class="dashboard-month-row" data-month="{{ $month['key'] }}">
                            <div class="small fw-semibold text-nowrap">{{ $month['label'] }}</div>
                            <div class="dashboard-month-bars">
                                @foreach ([
                                    ['key' => 'assets', 'label' => 'Biens', 'color' => 'primary'],
                                    ['key' => 'assignments', 'label' => 'Affectations', 'color' => 'success'],
                                    ['key' => 'movements', 'label' => 'Mouvements', 'color' => 'info'],
                                ] as $series)
                                    @php($value = $month[$series['key']])
                                    <div class="d-flex align-items-center gap-2 small" aria-label="{{ $series['label'] }} : {{ $value }}">
                                        <span class="dashboard-month-series-label">{{ $series['label'] }}</span>
                                        <div class="progress dashboard-month-track flex-grow-1" role="progressbar" aria-label="{{ $series['label'] }}" aria-valuenow="{{ $value }}" aria-valuemin="0" aria-valuemax="{{ $monthlyMax }}">
                                            <div class="progress-bar bg-{{ $series['color'] }}" style="width: {{ $value > 0 ? max(4, round($value * 100 / $monthlyMax)) : 0 }}%"></div>
                                        </div>
                                        <span class="text-secondary dashboard-month-value" data-month-{{ $series['key'] }}="{{ $value }}">{{ $value }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="text-secondary mb-0">Aucune donnée sur la période choisie.</p>
                    @endforelse
                    <div class="d-flex flex-wrap gap-3 small text-secondary mt-3">
                        <span><i class="bi bi-square-fill text-primary me-1"></i>Biens</span>
                        <span><i class="bi bi-square-fill text-success me-1"></i>Affectations</span>
                        <span><i class="bi bi-square-fill text-info me-1"></i>Mouvements</span>
                    </div>
                </div>
            </section>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-12 col-xl-7">
                <section class="card content-card h-100">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                            <div>
                                <h2 class="h5 mb-1">{{ $role->value === 'viewer' ? 'Derniers biens enregistrés' : 'Biens récemment enregistrés' }}</h2>
                                <p class="small text-secondary mb-0">Données de la période et des filtres sélectionnés.</p>
                            </div>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('assets.index') }}">Tous les biens</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">Référence</th>
                                        <th scope="col">Désignation</th>
                                        <th scope="col">Catégorie</th>
                                        <th scope="col">Statut</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($recentAssets as $asset)
                                        <tr>
                                            <td class="text-nowrap"><a class="fw-semibold" href="{{ route('assets.show', $asset) }}">{{ $asset->asset_code }}</a></td>
                                            <td>{{ $asset->designation }}</td>
                                            <td>{{ $asset->category->parent ? $asset->category->parent->name.' / ' : '' }}{{ $asset->category->name }}</td>
                                            <td>{{ $statusLabels[$asset->status->value] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td class="text-center text-secondary py-4" colspan="4">Aucun bien ne correspond à ces critères.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-12 col-xl-5">
                <section class="card content-card h-100">
                    <div class="card-body p-4">
                        <h2 class="h5 mb-3">Accès rapides</h2>
                        <div class="d-grid gap-2">
                            <a class="sidebar-link border" href="{{ route('assets.index') }}"><i class="bi bi-box-seam"></i><span>Consulter et rechercher les biens</span><i class="bi bi-chevron-right ms-auto"></i></a>
                            <a class="sidebar-link border" href="{{ route('assignments.index') }}"><i class="bi bi-arrow-left-right"></i><span>Consulter les affectations</span><i class="bi bi-chevron-right ms-auto"></i></a>
                            @can('manage-assets')
                                <a class="sidebar-link border" href="{{ route('references.categories.index') }}"><i class="bi bi-tags"></i><span>Gérer les catégories</span><i class="bi bi-chevron-right ms-auto"></i></a>
                                <a class="sidebar-link border" href="{{ route('references.locations.index') }}"><i class="bi bi-geo-alt"></i><span>Gérer les localisations</span><i class="bi bi-chevron-right ms-auto"></i></a>
                            @endcan
                            @can('manage-assignments')
                                <a class="sidebar-link border" href="{{ route('assignments.create') }}"><i class="bi bi-person-plus"></i><span>Enregistrer une affectation</span><i class="bi bi-chevron-right ms-auto"></i></a>
                            @endcan
                            @can('manage-users')
                                <a class="sidebar-link border" href="{{ route('users.index') }}"><i class="bi bi-people"></i><span>Administrer les comptes et rôles</span><i class="bi bi-chevron-right ms-auto"></i></a>
                            @endcan
                        </div>
                    </div>
                </section>
            </div>
        </div>

        @if (isset($recentAssignments) || isset($recentMovements) || isset($recentEdits))
            <div class="row g-3">
                @if (isset($recentAssignments))
                    <div class="col-12 col-xl-4">
                        <section class="card content-card h-100">
                            <div class="card-body p-4">
                                <h2 class="h5 mb-3">Affectations récentes</h2>
                                <p class="small text-secondary">Les transferts apparaissent avec la nouvelle affectation et le motif enregistré.</p>
                                @forelse ($recentAssignments as $assignment)
                                    <a class="dashboard-activity-link" href="{{ route('assignments.show', $assignment->asset) }}">
                                        <span class="fw-semibold">{{ $assignment->asset->asset_code }}</span>
                                        <span class="small text-secondary d-block">{{ $assignment->agent?->last_name ? $assignment->agent->first_name.' '.$assignment->agent->last_name : $assignment->service?->name ?? 'Affectation enregistrée' }}</span>
                                        <span class="small text-secondary d-block">{{ $assignment->reason }}</span>
                                        <span class="small text-secondary">{{ $assignment->assigned_at->format('d/m/Y H:i') }}</span>
                                    </a>
                                @empty
                                    <p class="text-secondary small mb-0">Aucune affectation enregistrée pour ces critères.</p>
                                @endforelse
                            </div>
                        </section>
                    </div>
                @endif
                @if (isset($recentCompletedAssignments))
                    <div class="col-12 col-xl-4">
                        <section class="card content-card h-100">
                            <div class="card-body p-4">
                                <h2 class="h5 mb-3">Fins d’affectation enregistrées</h2>
                                <p class="small text-secondary">Motif et date de fin sont affichés tels qu’enregistrés; le schéma ne distingue pas séparément le type d’événement.</p>
                                @forelse ($recentCompletedAssignments as $assignment)
                                    <a class="dashboard-activity-link" href="{{ route('assignments.show', $assignment->asset) }}">
                                        <span class="fw-semibold">{{ $assignment->asset->asset_code }}</span>
                                        <span class="small text-secondary d-block">{{ $assignment->agent?->last_name ? $assignment->agent->first_name.' '.$assignment->agent->last_name : $assignment->service?->name ?? 'Détenteur non renseigné' }}</span>
                                        <span class="small text-secondary d-block">{{ $assignment->end_reason ?? 'Motif de fin non renseigné' }}</span>
                                        <span class="small text-secondary">{{ $assignment->ended_at->format('d/m/Y H:i') }} · {{ $assignment->ender?->name ?? 'Utilisateur non renseigné' }}</span>
                                    </a>
                                @empty
                                    <p class="text-secondary small mb-0">Aucune fin d’affectation enregistrée pour ces critères.</p>
                                @endforelse
                            </div>
                        </section>
                    </div>
                @endif
                @if (isset($recentMovements))
                    <div class="col-12 col-xl-4">
                        <section class="card content-card h-100">
                            <div class="card-body p-4">
                                <h2 class="h5 mb-3">Mouvements récents</h2>
                                @forelse ($recentMovements as $movement)
                                    <a class="dashboard-activity-link" href="{{ route('assets.show', $movement->asset) }}">
                                        <span class="fw-semibold">{{ $movement->asset->asset_code }}</span>
                                        <span class="small text-secondary d-block">{{ $movement->fromLocation?->name ?? 'Origine initiale' }} → {{ $movement->toLocation?->name ?? 'Localisation retirée' }}</span>
                                        <span class="small text-secondary">{{ $movement->moved_at->format('d/m/Y H:i') }} · {{ $movement->reason }}</span>
                                    </a>
                                @empty
                                    <p class="text-secondary small mb-0">Aucun mouvement enregistré pour ces critères.</p>
                                @endforelse
                            </div>
                        </section>
                    </div>
                @endif
                @if (isset($recentEdits))
                    <div class="col-12 col-xl-4">
                        <section class="card content-card h-100">
                            <div class="card-body p-4">
                                <h2 class="h5 mb-3">Biens récemment modifiés</h2>
                                @forelse ($recentEdits as $asset)
                                    <a class="dashboard-activity-link" href="{{ route('assets.show', $asset) }}">
                                        <span class="fw-semibold">{{ $asset->asset_code }} · {{ $asset->designation }}</span>
                                        <span class="small text-secondary d-block">{{ $asset->updater?->name ?? 'Utilisateur non renseigné' }}</span>
                                        <span class="small text-secondary">{{ $asset->updated_at->format('d/m/Y H:i') }}</span>
                                    </a>
                                @empty
                                    <p class="text-secondary small mb-0">Aucune modification traçable pour ces critères.</p>
                                @endforelse
                            </div>
                        </section>
                    </div>
                @endif
            </div>
        @endif
    </div>
@endsection
