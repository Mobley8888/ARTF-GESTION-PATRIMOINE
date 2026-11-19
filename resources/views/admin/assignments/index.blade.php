@extends('admin.layout')

@section('title', 'Affectations')

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <p class="text-primary text-uppercase fw-semibold small mb-1">Mouvements administratifs</p>
            <h1 class="h3 mb-1">Affectations actives</h1>
            <p class="text-secondary mb-0">{{ $assignments->total() }} bien(s) actuellement affecté(s)</p>
        </div>
        @can('manage-assignments')
            <a class="btn btn-primary flex-shrink-0" href="{{ route('assignments.create') }}"><i class="bi bi-plus-lg me-1"></i> Affecter un bien</a>
        @endcan
        <a class="btn btn-outline-primary flex-shrink-0" href="{{ route('assignments.unassigned') }}">Biens non affectés</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <section class="card content-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('assignments.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="q">Bien</label>
                        <input class="form-control" id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Code ou désignation">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="agent_id">Agent</label>
                        <select class="form-select" id="agent_id" name="agent_id">
                            <option value="">Tous</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected(($filters['agent_id'] ?? '') == $agent->id)>{{ $agent->last_name }} {{ $agent->first_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="service_id">Service</label>
                        <select class="form-select" id="service_id" name="service_id">
                            <option value="">Tous</option>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}" @selected(($filters['service_id'] ?? '') == $service->id)>{{ $service->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Filtrer</button>
                        <a class="btn btn-outline-secondary" href="{{ route('assignments.index') }}">Effacer</a>
                    </div>
                    <div class="col-12 col-md-3"><label class="form-label" for="date_from">À partir du</label><input class="form-control" type="date" id="date_from" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
                    <div class="col-12 col-md-3"><label class="form-label" for="date_to">Jusqu’au</label><input class="form-control" type="date" id="date_to" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
                    <div class="col-12 col-md-4"><label class="form-label" for="location_id">Localisation physique</label><select class="form-select" id="location_id" name="location_id"><option value="">Toutes</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected(($filters['location_id'] ?? '') == $location->id)>{{ $location->name }}</option>@endforeach</select></div>
                    <div class="col-12 col-md-3"><label class="form-label" for="movement_type">Type de mouvement</label><select class="form-select" id="movement_type" name="movement_type"><option value="">Tous</option><option value="assignment" @selected(($filters['movement_type'] ?? '') === 'assignment')>Affectation</option><option value="transfer" @selected(($filters['movement_type'] ?? '') === 'transfer')>Transfert</option></select></div>
                </div>
            </form>
        </div>
    </section>

    <section class="card content-card assignment-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Bien</th>
                        <th>Détenteur administratif</th>
                        <th>Mouvement</th>
                        <th>Pris en charge le</th>
                        <th>Enregistré par</th>
                        <th><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($assignments as $assignment)
                        <tr>
                            <td class="fw-semibold text-nowrap">{{ $assignment->asset->asset_code }}</td>
                            <td>{{ $assignment->asset->designation }}</td>
                            <td>
                                @if ($assignment->agent)
                                    <a href="{{ route('assignments.agent', $assignment->agent) }}">{{ $assignment->agent->last_name }} {{ $assignment->agent->first_name }}</a>
                                @else
                                    <a href="{{ route('assignments.service', $assignment->service) }}">{{ $assignment->service->name }}</a>
                                @endif
                            </td>
                            <td>{{ $assignment->movement_type === 'transfer' ? 'Transfert' : 'Affectation' }}</td>
                            <td class="text-nowrap">{{ $assignment->assigned_at->format('d/m/Y H:i') }}</td>
                            <td>{{ $assignment->assigner->name }}</td>
                            <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('assignments.show', $assignment->asset) }}">Historique</a></td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-5" colspan="7">Aucune affectation active ne correspond à ces critères.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($assignments->hasPages())
            <div class="card-body border-top">{{ $assignments->links() }}</div>
        @endif
    </section>
@endsection
