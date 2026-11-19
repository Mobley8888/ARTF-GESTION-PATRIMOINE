@extends('admin.layout')

@section('title', 'Nouvelle affectation')

@section('content')
    <div class="mb-4">
        <a class="small text-decoration-none" href="{{ route('assignments.index') }}"><i class="bi bi-arrow-left me-1"></i>Retour aux affectations</a>
        <h1 class="h3 mt-2 mb-1">Affecter un bien</h1>
        <p class="text-secondary mb-0">Une affectation concerne un agent ou un service, jamais les deux à la fois.</p>
    </div>
    <section class="card content-card">
        <div class="card-body p-3 p-md-4">
            @include('admin.assignments._form', [
                'action' => route('assignments.store'),
                'asset' => null,
                'includeAsset' => true,
                'submitLabel' => 'Enregistrer l’affectation',
            ])
        </div>
    </section>
@endsection
