@extends('admin.layout')

@section('title', 'Nouveau bien')

@section('content')
    <div class="mb-4">
        <a class="small text-decoration-none" href="{{ route('assets.index') }}"><i class="bi bi-arrow-left me-1"></i>Retour aux biens</a>
        <h1 class="h3 mt-2 mb-1">Nouveau bien</h1>
        <p class="text-secondary mb-0">Les champs sont enregistrés selon le modèle patrimonial existant.</p>
    </div>

    @if ($categories->isEmpty())
        <div class="alert alert-warning" role="alert">
            Aucune catégorie active n’est enregistrée dans le référentiel. Un bien ne peut pas être créé sans catégorie.
            <a class="alert-link" href="{{ route('references.categories.create') }}">Créer une catégorie</a> avec les données validées.
        </div>
    @endif

    <section class="card content-card">
        <div class="card-body p-3 p-md-4">
            @include('admin.assets._form')
        </div>
    </section>
@endsection
