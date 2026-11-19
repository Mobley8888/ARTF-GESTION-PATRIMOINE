@extends('admin.layout')

@section('title', 'Inventaires physiques')

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <p class="text-primary text-uppercase fw-semibold small mb-1">Contrôle du patrimoine</p>
            <h1 class="h3 mb-1">Inventaires physiques</h1>
            <p class="text-secondary mb-0">Campagnes, progression des constats et écarts.</p>
        </div>
        @can('manage-inventories')
            <a class="btn btn-primary" href="{{ route('inventories.create') }}"><i class="bi bi-plus-lg me-1"></i>Nouvelle campagne</a>
        @endcan
    </div>

    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4"><div class="card content-card h-100"><div class="card-body"><div class="small text-secondary">Campagnes</div><div class="fs-3 fw-semibold">{{ $totals['all'] }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card content-card h-100"><div class="card-body"><div class="small text-secondary">En cours ou à vérifier</div><div class="fs-3 fw-semibold">{{ $totals['active'] }}</div></div></div></div>
        <div class="col-12 col-md-4"><div class="card content-card h-100"><div class="card-body"><div class="small text-secondary">Clôturées</div><div class="fs-3 fw-semibold">{{ $totals['closed'] }}</div></div></div></div>
    </div>

    <section class="card content-card mb-4"><div class="card-body">
        <form method="GET" action="{{ route('inventories.index') }}" class="row g-3 align-items-end">
            <div class="col-12 col-md-6"><label class="form-label" for="q">Recherche</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Référence ou intitulé"></div>
            <div class="col-12 col-md-4"><label class="form-label" for="status">Statut</label><select class="form-select" id="status" name="status"><option value="">Tous</option>@foreach ($statusLabels as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-12 col-md-2 d-flex gap-2"><button class="btn btn-primary" type="submit">Filtrer</button><a class="btn btn-outline-secondary" href="{{ route('inventories.index') }}">Effacer</a></div>
        </form>
    </div></section>

    <section class="card content-card"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Référence</th><th>Campagne</th><th>Période</th><th>Périmètres</th><th>Progression</th><th>Responsable</th><th>Statut</th><th></th></tr></thead>
            <tbody>
                @forelse ($campaigns as $campaign)
                    <tr>
                        <td class="fw-semibold text-nowrap">{{ $campaign->reference }}</td><td>{{ $campaign->title }}</td>
                        <td class="text-nowrap">{{ $campaign->starts_on->format('d/m/Y') }} — {{ $campaign->ends_on->format('d/m/Y') }}</td>
                        <td>{{ $campaign->scopes_count }}</td>
                        <td>{{ $campaign->items_count ? number_format(($campaign->items_count - $campaign->items()->where('status', 'pending')->count()) / $campaign->items_count * 100, 0) : 0 }}% <span class="small text-secondary">({{ $campaign->items_count }} biens)</span></td>
                        <td>{{ $campaign->responsible->name }}</td>
                        <td><span class="badge text-bg-light border">{{ $statusLabels[$campaign->status->value] }}</span></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('inventories.show', $campaign) }}">Ouvrir</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-secondary py-5">Aucune campagne ne correspond à ces critères.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>@if ($campaigns->hasPages())<div class="card-body border-top">{{ $campaigns->links() }}</div>@endif</section>
@endsection
