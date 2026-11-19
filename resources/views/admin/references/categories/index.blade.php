@extends('admin.layout')

@section('title', 'Catégories patrimoniales')

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <p class="text-primary text-uppercase fw-semibold small mb-1">Référentiels</p>
            <h1 class="h3 mb-1">Catégories et sous-catégories</h1>
            <p class="text-secondary mb-0">{{ $categories->total() }} catégorie(s)</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-secondary" href="{{ request()->fullUrl() }}"><i class="bi bi-arrow-clockwise me-1"></i>Actualiser</a>
            <a class="btn btn-primary" href="{{ route('references.categories.create') }}"><i class="bi bi-plus-lg me-1"></i>Nouvelle catégorie</a>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @error('category')
        <div class="alert alert-danger" role="alert">{{ $message }}</div>
    @enderror

    <section class="card content-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('references.categories.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="q">Recherche</label>
                        <input class="form-control" id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Code ou désignation">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="active">État</label>
                        <select class="form-select" id="active" name="active">
                            <option value="">Tous</option>
                            <option value="active" @selected(($filters['active'] ?? '') === 'active')>Actives</option>
                            <option value="inactive" @selected(($filters['active'] ?? '') === 'inactive')>Désactivées</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Filtrer</button>
                        <a class="btn btn-outline-secondary" href="{{ route('references.categories.index') }}">Réinitialiser</a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="card content-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Catégorie</th>
                        <th>Sous-catégorie de</th>
                        <th>Biens référencés</th>
                        <th>État</th>
                        <th><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td class="text-nowrap fw-semibold">{{ $category->code }}</td>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->parent?->name ?? '—' }}</td>
                            <td>{{ $category->assets_count }}</td>
                            <td><span class="badge {{ $category->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $category->is_active ? 'Active' : 'Désactivée' }}</span></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('references.categories.show', $category) }}">Consulter</a>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('references.categories.edit', $category) }}">Modifier</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-5" colspan="6">Aucune catégorie enregistrée. Les propositions de classification ne sont pas ajoutées automatiquement.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($categories->hasPages())
            <div class="card-body border-top">{{ $categories->links() }}</div>
        @endif
    </section>
@endsection
