@extends('admin.layout')

@section('title', 'Biens affectés')

@section('content')
    <div class="mb-4">
        <a class="small text-decoration-none" href="{{ route('assignments.index') }}"><i class="bi bi-arrow-left me-1"></i>Retour aux affectations</a>
        <p class="text-primary text-uppercase fw-semibold small mt-3 mb-1">{{ $subtitle }}</p>
        <h1 class="h3 mb-0">{{ $title }}</h1>
    </div>
    <section class="card content-card assignment-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Code</th><th>Désignation</th><th>Catégorie</th><th>Pris en charge le</th><th>Enregistré par</th><th><span class="visually-hidden">Action</span></th></tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $assignment)
                        <tr>
                            <td class="fw-semibold">{{ $assignment->asset->asset_code }}</td>
                            <td>{{ $assignment->asset->designation }}</td>
                            <td>{{ $assignment->asset->category->name }}</td>
                            <td class="text-nowrap">{{ $assignment->assigned_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $assignment->assigner->name }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('assignments.show', $assignment->asset) }}">Historique</a></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-5" colspan="6">Aucun bien actuellement affecté.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($assignments->hasPages())
            <div class="card-body border-top">{{ $assignments->links() }}</div>
        @endif
    </section>
@endsection
