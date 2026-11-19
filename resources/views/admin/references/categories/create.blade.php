@extends('admin.layout')

@section('title', 'Nouvelle catégorie')

@section('content')
    <div class="mb-4">
        <a class="small text-decoration-none" href="{{ route('references.categories.index') }}"><i class="bi bi-arrow-left me-1"></i>Retour aux catégories</a>
        <p class="text-primary text-uppercase fw-semibold small mt-3 mb-1">Référentiels</p>
        <h1 class="h3 mb-1">Nouvelle catégorie</h1>
        <p class="text-secondary mb-0">Les catégories proposées doivent être validées avant leur enregistrement.</p>
    </div>
    <section class="card content-card">
        <div class="card-body p-3 p-md-4">@include('admin.references.categories._form')</div>
    </section>
@endsection
