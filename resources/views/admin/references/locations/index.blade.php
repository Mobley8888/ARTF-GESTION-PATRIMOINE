@extends('admin.layout')

@section('title', 'Sites et localisations')

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <p class="text-primary text-uppercase fw-semibold small mb-1">Référentiels</p>
            <h1 class="h3 mb-1">Sites et localisations</h1>
            <p class="text-secondary mb-0">{{ $locations->total() }} lieu(x)</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ request()->fullUrl() }}"><i class="bi bi-arrow-clockwise me-1"></i>Actualiser</a>
            <a class="btn btn-primary" href="{{ route('references.locations.create') }}"><i class="bi bi-plus-lg me-1"></i>Nouveau lieu</a>
        </div>
    </div>

    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @error('location')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <section class="card content-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('references.locations.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-5">
                        <label class="form-label" for="q">Recherche</label>
                        <input class="form-control" id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Code, désignation ou adresse">
                    </div>
                    <div class="col-12 col-sm-6 col-md-3">
                        <label class="form-label" for="type">Niveau</label>
                        <select class="form-select" id="type" name="type">
                            <option value="">Tous</option>
                            @foreach ($typeOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-sm-6 col-md-2">
                        <label class="form-label" for="active">État</label>
                        <select class="form-select" id="active" name="active">
                            <option value="">Tous</option>
                            <option value="active" @selected(($filters['active'] ?? '') === 'active')>Actifs</option>
                            <option value="inactive" @selected(($filters['active'] ?? '') === 'inactive')>Désactivés</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Filtrer</button>
                        <a class="btn btn-outline-secondary" href="{{ route('references.locations.index') }}">Réinitialiser</a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="card content-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Code</th><th>Niveau</th><th>Lieu</th><th>Parent</th><th>Biens</th><th>État</th><th><span class="visually-hidden">Actions</span></th></tr>
                </thead>
                <tbody>
                    @forelse ($locations as $location)
                        <tr>
                            <td class="text-nowrap fw-semibold">{{ $location->code }}</td>
                            <td>{{ $location->type->label() }}</td>
                            <td>{{ $location->name }}</td>
                            <td>{{ collect([$location->parent?->parent?->name, $location->parent?->name])->filter()->implode(' / ') ?: '—' }}</td>
                            <td>{{ $location->assets_count }}</td>
                            <td><span class="badge {{ $location->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $location->is_active ? 'Actif' : 'Désactivé' }}</span></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('references.locations.show', $location) }}">Consulter</a>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('references.locations.edit', $location) }}">Modifier</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-5" colspan="7">Aucun site ou lieu n’est enregistré.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($locations->hasPages())
            <div class="card-body border-top">{{ $locations->links() }}</div>
        @endif
    </section>
@endsection
