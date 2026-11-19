@extends('admin.layout')

@section('title', 'Constats — '.$campaign->reference)

@section('content')
    <div class="mb-4"><a class="small text-decoration-none" href="{{ route('inventories.show', $campaign) }}">Retour à la campagne</a><p class="text-primary text-uppercase fw-semibold small mt-2 mb-1">{{ $campaign->reference }}</p><h1 class="h3 mb-1">Constats physiques</h1><p class="text-secondary mb-0">{{ $campaign->title }}</p></div>
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    <section class="card content-card mb-3"><div class="card-body"><form method="GET" action="{{ route('inventories.items', $campaign) }}" class="row g-3 align-items-end">
        <div class="col-12 col-md-5"><label class="form-label" for="q">Référence ou désignation</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}"></div>
        <div class="col-12 col-md-3"><label class="form-label" for="status">Statut</label><select class="form-select" id="status" name="status"><option value="">Tous</option>@foreach ($itemStatusLabels as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-12 col-md-2"><label class="form-label" for="direction_id">Direction</label><select class="form-select" id="direction_id" name="direction_id"><option value="">Toutes</option>@foreach ($directions as $direction)<option value="{{ $direction->id }}" @selected(($filters['direction_id'] ?? '') == $direction->id)>{{ $direction->name }}</option>@endforeach</select></div>
        <div class="col-12 col-md-2"><label class="form-label" for="service_id">Service</label><select class="form-select" id="service_id" name="service_id"><option value="">Tous</option>@foreach ($services as $service)<option value="{{ $service->id }}" @selected(($filters['service_id'] ?? '') == $service->id)>{{ $service->name }}</option>@endforeach</select></div>
        <div class="col-12 col-md-2"><label class="form-label" for="location_id">Localisation</label><select class="form-select" id="location_id" name="location_id"><option value="">Toutes</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected(($filters['location_id'] ?? '') == $location->id)>{{ $location->name }}</option>@endforeach</select></div>
        <div class="col-12 col-md-3 d-flex gap-2"><button class="btn btn-primary" type="submit">Filtrer</button><a class="btn btn-outline-secondary" href="{{ route('inventories.items', $campaign) }}">Effacer</a><a class="btn btn-outline-danger" href="{{ route('inventories.items', [$campaign, 'anomalies' => 1]) }}">Écarts</a></div>
    </form></div></section>

    <section class="card content-card"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Référence</th><th>Désignation</th><th>Localisation théorique / constatée</th><th>Agent</th><th>Statut</th><th></th></tr></thead>
            <tbody>
                @forelse ($items as $item)
                    @php($expected = $item->expected_snapshot ?? [])
                    @php($canEnter = auth()->user()->can('manage-inventories') || auth()->user()->agent?->getKey() === $item->task?->agent_id)
                    <tr>
                        <td class="fw-semibold">{{ $item->asset?->asset_code ?? $item->observed_asset_code ?? '—' }}</td>
                        <td>{{ $item->asset?->designation ?? $item->observed_designation ?? 'Bien découvert' }}</td>
                        <td><div>{{ $expected['location'] ?? 'Non renseignée' }}</div><div class="small text-secondary">Constatée : {{ $item->observedLocation?->name ?? '—' }}</div></td>
                        <td>{{ $item->task?->agent?->last_name }} {{ $item->task?->agent?->first_name }}</td>
                        <td><span class="badge {{ $item->status->value === 'pending' ? 'text-bg-secondary' : (in_array($item->status->value, ['present_conform'], true) ? 'text-bg-success' : 'text-bg-warning') }}">{{ $itemStatusLabels[$item->status->value] }}</span></td>
                        <td class="text-end"><div class="d-flex justify-content-end flex-wrap gap-1"><a class="btn btn-sm btn-outline-success" href="{{ route('documents.inventory.findings.show', $item) }}">Fiche</a><a class="btn btn-sm btn-outline-danger" href="{{ route('documents.inventory.findings.pdf', $item) }}">PDF</a><button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#item-{{ $item->id }}">Détail / saisir</button></div></td>
                    </tr>
                    <tr class="collapse" id="item-{{ $item->id }}"><td colspan="6"><div class="p-3 bg-light rounded">
                        <div class="row g-3 mb-3 small">
                            <div class="col-md-4"><strong>Théorique</strong><br>{{ $expected['asset_code'] ?? '—' }} · {{ $expected['designation'] ?? '—' }}<br>Localisation : {{ $expected['location'] ?? '—' }}<br>Détenteur : {{ $expected['agent'] ?? $expected['service'] ?? 'Non affecté' }}</div>
                            <div class="col-md-4"><strong>Constaté</strong><br>{{ $item->observed_asset_code ?? '—' }} · {{ $item->observed_designation ?? '—' }}<br>Localisation : {{ $item->observedLocation?->name ?? '—' }}<br>Détenteur : {{ $item->observedAgent ? $item->observedAgent->last_name.' '.$item->observedAgent->first_name : ($item->observedService?->name ?? '—') }}</div>
                            <div class="col-md-4"><strong>Anomalies</strong><br>Physique : {{ $item->physical_anomaly ?? 'Aucune saisie' }}<br>Administrative : {{ $item->administrative_anomaly ?? 'Aucune saisie' }}<br>Observations : {{ $item->observations ?? '—' }}</div>
                        </div>
                        @if ($canEnter && $campaign->status->value === 'in_progress' && $item->asset_id)
                            <form method="POST" action="{{ route('inventories.capture', $item) }}" class="row g-2 mb-3">@csrf @method('PUT')
                                <div class="col-12 col-md-3"><label class="form-label small" for="status-{{ $item->id }}">Statut du contrôle</label><select class="form-select form-select-sm" id="status-{{ $item->id }}" name="status" required>@foreach ($itemStatusLabels as $value => $label)@if ($value !== 'pending' && $value !== 'discovered')<option value="{{ $value }}" @selected($item->status->value === $value)>{{ $label }}</option>@endif @endforeach</select></div>
                                <div class="col-12 col-md-3"><label class="form-label small" for="observed-location-{{ $item->id }}">Localisation constatée</label><select class="form-select form-select-sm" id="observed-location-{{ $item->id }}" name="observed_location_id"><option value="">Non renseignée</option>@foreach ($locations as $location)<option value="{{ $location->id }}" @selected((string) $item->observed_location_id === (string) $location->id)>{{ $location->name }}</option>@endforeach</select></div>
                                <div class="col-12 col-md-3"><label class="form-label small" for="observed-agent-{{ $item->id }}">Agent détenteur constaté</label><select class="form-select form-select-sm" id="observed-agent-{{ $item->id }}" name="observed_agent_id"><option value="">Non renseigné</option>@foreach ($agents as $agent)<option value="{{ $agent->id }}" @selected((string) $item->observed_agent_id === (string) $agent->id)>{{ $agent->last_name }} {{ $agent->first_name }}</option>@endforeach</select></div>
                                <div class="col-12 col-md-3"><label class="form-label small" for="observed-service-{{ $item->id }}">Service détenteur constaté</label><select class="form-select form-select-sm" id="observed-service-{{ $item->id }}" name="observed_service_id"><option value="">Non renseigné</option>@foreach ($services as $service)<option value="{{ $service->id }}" @selected((string) $item->observed_service_id === (string) $service->id)>{{ $service->name }}</option>@endforeach</select></div>
                                <div class="col-12 col-md-4"><label class="form-label small" for="observed-code-{{ $item->id }}">Référence constatée</label><input class="form-control form-control-sm" id="observed-code-{{ $item->id }}" name="observed_asset_code" value="{{ old('observed_asset_code', $item->observed_asset_code ?? $expected['asset_code'] ?? '') }}" maxlength="60"></div>
                                <div class="col-12 col-md-4"><label class="form-label small" for="observed-designation-{{ $item->id }}">Désignation constatée</label><input class="form-control form-control-sm" id="observed-designation-{{ $item->id }}" name="observed_designation" value="{{ old('observed_designation', $item->observed_designation ?? $expected['designation'] ?? '') }}" maxlength="180"></div>
                                <div class="col-12 col-md-4"><label class="form-label small" for="physical-condition-{{ $item->id }}">État physique constaté</label><select class="form-select form-select-sm" id="physical-condition-{{ $item->id }}" name="physical_condition"><option value="">Non renseigné</option>@foreach (['new' => 'Neuf', 'good' => 'Bon', 'fair' => 'Moyen', 'poor' => 'Mauvais', 'unserviceable' => 'Hors service'] as $value => $label)<option value="{{ $value }}" @selected($item->physical_condition === $value)>{{ $label }}</option>@endforeach</select></div>
                                <div class="col-12 col-md-6"><label class="form-label small" for="physical-anomaly-{{ $item->id }}">Anomalie physique</label><textarea class="form-control form-control-sm" id="physical-anomaly-{{ $item->id }}" name="physical_anomaly" rows="2">{{ old('physical_anomaly', $item->physical_anomaly) }}</textarea></div>
                                <div class="col-12 col-md-6"><label class="form-label small" for="administrative-anomaly-{{ $item->id }}">Anomalie administrative</label><textarea class="form-control form-control-sm" id="administrative-anomaly-{{ $item->id }}" name="administrative_anomaly" rows="2">{{ old('administrative_anomaly', $item->administrative_anomaly) }}</textarea></div>
                                <div class="col-12"><label class="form-label small" for="observations-{{ $item->id }}">Observations</label><textarea class="form-control form-control-sm" id="observations-{{ $item->id }}" name="observations" rows="2">{{ old('observations', $item->observations) }}</textarea></div>
                                <div class="col-12"><button class="btn btn-sm btn-primary" type="submit">Enregistrer le constat</button></div>
                            </form>
                            <form method="POST" enctype="multipart/form-data" action="{{ route('inventories.evidence.store', $item) }}" class="d-flex flex-wrap align-items-end gap-2">@csrf<label class="form-label mb-0 flex-grow-1">Justificatifs (JPG, PNG, WebP ou PDF, 10 Mo max.)<input class="form-control form-control-sm mt-1" type="file" name="evidence[]" multiple accept=".jpg,.jpeg,.png,.webp,.pdf"></label><button class="btn btn-sm btn-outline-primary" type="submit">Ajouter</button></form>
                        @endif
                        @if ($item->evidence->isNotEmpty())<div class="mt-3"><strong class="small">Justificatifs</strong>@foreach ($item->evidence as $file)<div class="small"><a href="{{ route('inventories.evidence.download', $file) }}">{{ $file->original_name }}</a> · {{ $file->uploader?->name }}</div>@endforeach</div>@endif
                    </div></td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-5">Aucun constat ne correspond aux filtres.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>@if ($items->hasPages())<div class="card-body border-top">{{ $items->links() }}</div>@endif</section>
@endsection
