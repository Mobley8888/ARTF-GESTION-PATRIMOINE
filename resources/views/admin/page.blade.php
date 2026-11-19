@extends('admin.layout')

@section('title', $title)

@section('content')
    <div class="mb-4">
        <p class="text-primary text-uppercase fw-semibold small mb-1">Gestion du patrimoine</p>
        <h1 class="h3 mb-1">{{ $title }}</h1>
        <p class="text-secondary mb-0">{{ $description }}</p>
    </div>

    <section class="card content-card">
        <div class="card-body text-center py-5 px-3">
            <span class="metric-icon mx-auto mb-3"><i class="bi {{ $icon }}"></i></span>
            <h2 class="h5">Module prêt à être configuré</h2>
            <p class="text-secondary mb-0">Aucune donnée n’est encore enregistrée dans cette section.</p>
        </div>
    </section>
@endsection
