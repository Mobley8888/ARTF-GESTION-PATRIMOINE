@extends('admin.layout')

@section('title', 'Modifier '.$location->name)

@section('content')
    <div class="mb-4">
        <a class="small text-decoration-none" href="{{ route('references.locations.show', $location) }}"><i class="bi bi-arrow-left me-1"></i>Retour au lieu</a>
        <p class="text-primary text-uppercase fw-semibold small mt-3 mb-1">Référentiels</p>
        <h1 class="h3 mb-1">Modifier le lieu</h1>
        <p class="text-secondary mb-0">{{ $location->code }} — {{ $location->name }}</p>
    </div>
    <section class="card content-card">
        <div class="card-body p-3 p-md-4">@include('admin.references.locations._form')</div>
    </section>
@endsection
