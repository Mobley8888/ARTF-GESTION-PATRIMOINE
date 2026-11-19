@extends('admin.layout')

@section('title', 'Modifier '.$asset->asset_code)

@section('content')
    <div class="mb-4">
        <a class="small text-decoration-none" href="{{ route('assets.show', $asset) }}"><i class="bi bi-arrow-left me-1"></i>Retour à la fiche</a>
        <h1 class="h3 mt-2 mb-1">Modifier le bien</h1>
        <p class="text-secondary mb-0">{{ $asset->asset_code }} — {{ $asset->designation }}</p>
    </div>

    <section class="card content-card">
        <div class="card-body p-3 p-md-4">
            @include('admin.assets._form')
        </div>
    </section>
@endsection
