@extends('admin.layout')

@section('title', $category->name)

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <a class="small text-decoration-none" href="{{ route('references.categories.index') }}"><i class="bi bi-arrow-left me-1"></i>Retour aux catégories</a>
            <p class="text-primary text-uppercase fw-semibold small mt-3 mb-1">Référentiels</p>
            <h1 class="h3 mb-1">{{ $category->name }}</h1>
            <p class="text-secondary mb-0">{{ $category->code }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary" href="{{ route('references.categories.edit', $category) }}"><i class="bi bi-pencil me-1"></i>Modifier</a>
            <form method="POST" action="{{ route('references.categories.status', $category) }}" onsubmit="return confirm('Confirmer le changement d’état de cette catégorie ?')">
                @csrf
                @method('PATCH')
                <button class="btn {{ $category->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}" type="submit">{{ $category->is_active ? 'Désactiver' : 'Réactiver' }}</button>
            </form>
        </div>
    </div>

    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @error('category')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <section class="card content-card">
        <div class="card-body p-3 p-md-4">
            <dl class="row mb-0">
                <dt class="col-sm-4 text-secondary fw-normal">Code</dt><dd class="col-sm-8">{{ $category->code }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Catégorie parente</dt><dd class="col-sm-8">{{ $category->parent?->name ?? 'Catégorie principale' }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">État</dt><dd class="col-sm-8"><span class="badge {{ $category->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $category->is_active ? 'Active' : 'Désactivée' }}</span></dd>
                <dt class="col-sm-4 text-secondary fw-normal">Sous-catégories</dt><dd class="col-sm-8">{{ $category->children_count }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Biens référencés</dt><dd class="col-sm-8">{{ $category->assets_count }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Description</dt><dd class="col-sm-8">{{ $category->description ?: '—' }}</dd>
            </dl>
        </div>
    </section>
@endsection
