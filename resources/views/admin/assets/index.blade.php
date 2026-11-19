@extends('admin.layout')

@section('title', 'Biens patrimoniaux')

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <p class="text-primary text-uppercase fw-semibold small mb-1">Référentiel</p>
            <h1 class="h3 mb-1">Biens patrimoniaux</h1>
            <p class="text-secondary mb-0">{{ $assets->total() }} bien(s) trouvé(s)</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary flex-shrink-0" href="{{ request()->fullUrl() }}">
                <i class="bi bi-arrow-clockwise me-1"></i> Actualiser
            </a>
            @can('manage-assets')
                <a class="btn btn-primary flex-shrink-0" href="{{ route('assets.create') }}">
                    <i class="bi bi-plus-lg me-1"></i> Nouveau bien
                </a>
            @else
                <div class="text-end">
                    <button class="btn btn-outline-primary" type="button" disabled aria-disabled="true">
                        <i class="bi bi-plus-lg me-1"></i> Nouveau bien
                    </button>
                    <p class="small text-secondary mb-0 mt-1">Création réservée aux gestionnaires habilités.</p>
                </div>
            @endcan
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="status">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
    @endif

    <section class="card content-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('assets.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-6 col-xl-4">
                        <label class="form-label" for="q">Recherche</label>
                        <input class="form-control" id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Référence, désignation, série, marque, local, service…">
                    </div>
                    <div class="col-12 col-sm-6 col-xl-2">
                        <label class="form-label" for="category_id">Catégorie</label>
                        <select class="form-select" id="category_id" name="category_id">
                            <option value="">Toutes</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>
                                    {{ $category->parent ? $category->parent->name.' / ' : '' }}{{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-2">
                        <label class="form-label" for="site_id">Site</label>
                        <select class="form-select" id="site_id" name="site_id">
                            <option value="">Tous</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}" @selected(($filters['site_id'] ?? '') == $site->id)>{{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-2">
                        <label class="form-label" for="location_id">Localisation</label>
                        <select class="form-select" id="location_id" name="location_id">
                            <option value="">Toutes</option>
                            @foreach ($locations as $location)
                                @php($locationLabel = collect([$location->parent?->parent?->name, $location->parent?->name, $location->name])->filter()->implode(' / '))
                                <option value="{{ $location->id }}" @selected(($filters['location_id'] ?? '') == $location->id)>{{ $locationLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-2">
                        <label class="form-label" for="condition">État</label>
                        <select class="form-select" id="condition" name="condition">
                            <option value="">Tous</option>
                            @foreach ($conditionLabels as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['condition'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-2">
                        <label class="form-label" for="status">Statut</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Tous</option>
                            @foreach ($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-2">
                        <label class="form-label" for="service_id">Service détenteur</label>
                        <select class="form-select" id="service_id" name="service_id">
                            <option value="">Tous</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected(($filters['service_id'] ?? '') == $service->id)>{{ $service->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-xl-2">
                        <label class="form-label" for="usage_status">Situation d’utilisation</label>
                        <select class="form-select" id="usage_status" name="usage_status">
                            <option value="">Toutes</option>
                            @foreach (['in_service' => 'En service', 'broken' => 'En panne', 'under_repair' => 'En réparation', 'retired' => 'Réformé', 'other' => 'Autre'] as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['usage_status'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i> Filtrer</button>
                        <a class="btn btn-outline-secondary" href="{{ route('assets.index') }}">Réinitialiser</a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="card content-card asset-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th scope="col">Code</th>
                        <th scope="col">Désignation</th>
                        <th scope="col">Catégorie</th>
                        <th scope="col">Localisation</th>
                        <th scope="col">État</th>
                        <th scope="col">Statut</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assets as $asset)
                        @php($locationLabel = $asset->location ? collect([$asset->location->parent?->parent?->name, $asset->location->parent?->name, $asset->location->name])->filter()->implode(' / ') : null)
                        <tr>
                            <td class="fw-semibold text-nowrap">{{ $asset->asset_code }}</td>
                            <td>{{ $asset->designation }}</td>
                            <td>{{ $asset->category->parent ? $asset->category->parent->name.' / ' : '' }}{{ $asset->category->name }}</td>
                            <td>{{ $locationLabel ?: 'Non renseignée' }}</td>
                            <td>{{ $asset->condition ? $conditionLabels[$asset->condition->value] : 'Non renseigné' }}</td>
                            <td><span class="badge text-bg-light border">{{ $statusLabels[$asset->status->value] }}</span></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('assets.show', $asset) }}">Consulter</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="text-center text-secondary py-5" colspan="7">Aucun bien ne correspond à ces critères.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($assets->hasPages())
            <div class="card-body border-top">{{ $assets->links() }}</div>
        @endif
    </section>
@endsection
