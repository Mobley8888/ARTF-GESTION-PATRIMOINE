@extends('admin.layout')

@section('title', 'Biens non affectés')

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
        <div>
            <a class="small text-decoration-none" href="{{ route('assignments.index') }}">Voir les affectations actives</a>
            <h1 class="h3 mt-2">Biens non affectés</h1>
            <p class="text-secondary mb-0">{{ $assets->total() }} bien(s) sans détenteur administratif actif.</p>
        </div>
        @can('manage-assignments')
            <a class="btn btn-primary align-self-start" href="{{ route('assignments.create') }}">Affecter un bien</a>
        @endcan
    </div>
    <section class="card content-card"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Référence</th><th>Désignation</th><th>Catégorie</th><th>Localisation physique</th><th></th></tr></thead>
            <tbody>
                @forelse ($assets as $asset)
                    <tr>
                        <td class="fw-semibold">{{ $asset->asset_code }}</td><td>{{ $asset->designation }}</td>
                        <td>{{ $asset->category->name }}</td>
                        <td>{{ collect([$asset->location?->parent?->parent?->name, $asset->location?->parent?->name, $asset->location?->name])->filter()->implode(' / ') ?: 'Non renseignée' }}</td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('assignments.show', $asset) }}">Consulter</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-secondary py-5">Tous les biens ont une affectation active.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>@if ($assets->hasPages())<div class="card-body border-top">{{ $assets->links() }}</div>@endif</section>
@endsection
