@extends('admin.layout')

@section('title', 'Maintenance')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div><p class="text-primary text-uppercase small fw-semibold mb-1">Agence de Régulation des Transferts de Fonds (ARTF) · République du Congo</p><h1 class="h3 mb-1">Maintenance du patrimoine</h1><p class="text-secondary mb-0">Suivi des pannes, interventions et entretiens périodiques.</p></div>
        <div class="d-flex flex-wrap gap-2"><a class="btn btn-primary" href="{{ route('maintenance.requests.create') }}">Déclarer une panne</a>@can('manage-maintenance-schedules')<a class="btn btn-outline-primary" href="{{ route('maintenance.schedules') }}">Programmes préventifs</a>@endcan</div>
    </div>

    @if (session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="row g-3 mb-4">
        @foreach ([['total', 'Demandes visibles', 'bi-inboxes'], ['pending', 'En attente', 'bi-hourglass-split'], ['in_progress', 'Planifiées / en cours', 'bi-tools'], ['completed', 'Terminées', 'bi-check2-circle'], ['broken', 'Biens avec panne ouverte', 'bi-exclamation-triangle'], ['upcoming', 'Entretiens sous 30 jours', 'bi-calendar-event'], ['overdue', 'Entretiens en retard', 'bi-calendar-x'], ['corrective', 'Correctives', 'bi-wrench'], ['preventive', 'Préventives', 'bi-arrow-repeat']] as [$key, $label, $icon])
            @if ($stats[$key] !== null)<div class="col-6 col-md-4 col-xl-3"><div class="card content-card h-100"><div class="card-body d-flex align-items-center gap-3"><span class="metric-icon"><i class="bi {{ $icon }}"></i></span><div><div class="small text-secondary">{{ $label }}</div><div class="h4 mb-0">{{ number_format($stats[$key], 0, ',', ' ') }}</div></div></div></div></div>@endif
        @endforeach
        @can('view-maintenance-costs')<div class="col-6 col-md-4 col-xl-3"><div class="card content-card h-100"><div class="card-body"><div class="small text-secondary">Coûts clôturés</div><div class="h4 mb-0">{{ number_format($stats['costs'], 2, ',', ' ') }} FCFA</div><a class="small" href="{{ route('maintenance.costs') }}">Consulter les coûts</a></div></div></div>@endcan
    </div>

    <section class="card content-card mb-4"><div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3"><label class="form-label" for="q">Recherche</label><input class="form-control" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Référence, bien, panne"></div>
            <div class="col-md-2"><label class="form-label" for="type">Type</label><select class="form-select" id="type" name="type"><option value="">Tous</option><option value="corrective" @selected(($filters['type'] ?? '') === 'corrective')>Corrective</option><option value="preventive" @selected(($filters['type'] ?? '') === 'preventive')>Préventive</option></select></div>
            <div class="col-md-2"><label class="form-label" for="status">Statut</label><select class="form-select" id="status" name="status"><option value="">Tous</option>@foreach (\App\Enums\MaintenanceRequestStatus::cases() as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="direction_id">Direction</label><select class="form-select" id="direction_id" name="direction_id"><option value="">Toutes</option>@foreach ($directions as $direction)<option value="{{ $direction->id }}" @selected(($filters['direction_id'] ?? '') == $direction->id)>{{ $direction->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label" for="service_id">Service</label><select class="form-select" id="service_id" name="service_id"><option value="">Tous</option>@foreach ($services as $service)<option value="{{ $service->id }}" @selected(($filters['service_id'] ?? '') == $service->id)>{{ $service->name }}</option>@endforeach</select></div>
            <div class="col-md-1"><button class="btn btn-outline-primary w-100">Filtrer</button></div>
            <div class="col-md-2"><label class="form-label" for="from">Du</label><input class="form-control" type="date" id="from" name="from" value="{{ $filters['from'] ?? '' }}"></div><div class="col-md-2"><label class="form-label" for="to">Au</label><input class="form-control" type="date" id="to" name="to" value="{{ $filters['to'] ?? '' }}"></div>
        </form>
    </div></section>

    <section class="card content-card"><div class="card-body p-0">
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Référence</th><th>Type / priorité</th><th>Bien</th><th>Responsable</th><th>Signalement</th><th>Statut</th><th></th></tr></thead><tbody>
            @forelse ($requests as $item)
                <tr><td><a class="fw-semibold" href="{{ route('maintenance.requests.show', $item) }}">{{ $item->reference }}</a><div class="small text-secondary">{{ $item->title }}</div></td><td>{{ $item->type === 'corrective' ? 'Corrective' : 'Préventive' }}<div class="small text-secondary">{{ ['low' => 'Faible', 'normal' => 'Normale', 'high' => 'Élevée', 'urgent' => 'Urgente'][$item->priority] }}</div></td><td><a href="{{ route('assets.show', $item->asset) }}">{{ $item->asset->asset_code }}</a><div class="small text-secondary">{{ $item->asset->designation }}</div></td><td>{{ $item->assignedAgent ? $item->assignedAgent->last_name.' '.$item->assignedAgent->first_name : 'À désigner' }}</td><td>{{ $item->reported_at->format('d/m/Y H:i') }}</td><td><span class="badge text-bg-light">{{ $item->status->label() }}</span></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('maintenance.requests.show', $item) }}">Consulter</a></td></tr>
            @empty<tr><td colspan="7" class="text-center text-secondary py-5">Aucune demande ne correspond aux filtres.</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-3">{{ $requests->links() }}</div>
    </div></section>
@endsection
