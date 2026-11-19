@extends('admin.layout')

@section('title', 'Modifier '.$category->name)

@section('content')
    <div class="mb-4">
        <a class="small text-decoration-none" href="{{ route('references.categories.show', $category) }}"><i class="bi bi-arrow-left me-1"></i>Retour à la catégorie</a>
        <p class="text-primary text-uppercase fw-semibold small mt-3 mb-1">Référentiels</p>
        <h1 class="h3 mb-1">Modifier la catégorie</h1>
        <p class="text-secondary mb-0">{{ $category->code }} — {{ $category->name }}</p>
    </div>
    <section class="card content-card">
        <div class="card-body p-3 p-md-4">@include('admin.references.categories._form')</div>
    </section>
@endsection
