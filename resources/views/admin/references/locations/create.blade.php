@extends('admin.layout')

@section('title', 'Nouveau lieu')

@section('content')
    <div class="mb-4">
        <a class="small text-decoration-none" href="{{ route('references.locations.index') }}"><i class="bi bi-arrow-left me-1"></i>Retour aux localisations</a>
        <p class="text-primary text-uppercase fw-semibold small mt-3 mb-1">Référentiels</p>
        <h1 class="h3 mb-1">Nouveau site ou lieu</h1>
        <p class="text-secondary mb-0">La hiérarchie décrit les lieux physiques des biens (site, bâtiment, local), pas le rattachement administratif d’un agent.</p>
    </div>
    <section class="card content-card">
        <div class="card-body p-3 p-md-4">@include('admin.references.locations._form')</div>
    </section>
@endsection
