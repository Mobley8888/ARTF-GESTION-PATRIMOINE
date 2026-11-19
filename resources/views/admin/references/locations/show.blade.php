@extends('admin.layout')

@section('title', $location->name)

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <a class="small text-decoration-none" href="{{ route('references.locations.index') }}"><i class="bi bi-arrow-left me-1"></i>Retour aux localisations</a>
            <p class="text-primary text-uppercase fw-semibold small mt-3 mb-1">Référentiels</p>
            <h1 class="h3 mb-1">{{ $location->name }}</h1>
            <p class="text-secondary mb-0">{{ $location->code }} · {{ $location->type->label() }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-primary" href="{{ route('references.locations.edit', $location) }}"><i class="bi bi-pencil me-1"></i>Modifier</a>
            <form method="POST" action="{{ route('references.locations.status', $location) }}" onsubmit="return confirm('Confirmer le changement d’état de ce lieu ?')">
                @csrf
                @method('PATCH')
                <button class="btn {{ $location->is_active ? 'btn-outline-danger' : 'btn-outline-success' }}" type="submit">{{ $location->is_active ? 'Désactiver' : 'Réactiver' }}</button>
            </form>
        </div>
    </div>

    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @error('location')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

    <section class="card content-card">
        <div class="card-body p-3 p-md-4">
            <dl class="row mb-0">
                <dt class="col-sm-4 text-secondary fw-normal">Code</dt><dd class="col-sm-8">{{ $location->code }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Niveau</dt><dd class="col-sm-8">{{ $location->type->label() }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Lieu parent</dt><dd class="col-sm-8">{{ collect([$location->parent?->parent?->name, $location->parent?->name])->filter()->implode(' / ') ?: 'Aucun (site)' }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">État</dt><dd class="col-sm-8"><span class="badge {{ $location->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $location->is_active ? 'Actif' : 'Désactivé' }}</span></dd>
                <dt class="col-sm-4 text-secondary fw-normal">Biens référencés</dt><dd class="col-sm-8">{{ $location->assets_count }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Lieux enfants</dt><dd class="col-sm-8">{{ $location->children_count }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Historique de localisation</dt><dd class="col-sm-8">{{ $location->source_history_count + $location->destination_history_count }} mouvement(s)</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Adresse</dt><dd class="col-sm-8">{{ $location->address ?: '—' }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Superficie</dt><dd class="col-sm-8">{{ $location->surface_m2 ? $location->surface_m2.' m²' : '—' }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Description</dt><dd class="col-sm-8">{{ $location->description ?: '—' }}</dd>
            </dl>
        </div>
    </section>
@endsection
