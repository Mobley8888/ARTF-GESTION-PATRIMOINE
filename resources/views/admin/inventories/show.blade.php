@extends('admin.layout')

@section('title', $campaign->reference)

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div><a class="small text-decoration-none" href="{{ route('inventories.index') }}">Retour aux campagnes</a><p class="text-primary text-uppercase fw-semibold small mt-2 mb-1">{{ $campaign->reference }}</p><h1 class="h3 mb-1">{{ $campaign->title }}</h1><p class="text-secondary mb-0">{{ $campaign->starts_on->format('d/m/Y') }} — {{ $campaign->ends_on->format('d/m/Y') }} · Responsable : {{ $campaign->responsible->name }}</p></div>
        <div class="d-flex flex-wrap gap-2"><span class="badge text-bg-light border align-self-center">{{ ['draft' => 'Brouillon', 'in_progress' => 'En cours', 'to_verify' => 'À vérifier', 'validated' => 'Validée', 'closed' => 'Clôturée'][$campaign->status->value] }}</span>@if (! $isRestrictedAgent)<a class="btn btn-outline-success" href="{{ route('inventories.report.excel', $campaign) }}">Excel</a><a class="btn btn-outline-danger" href="{{ route('inventories.report.pdf', $campaign) }}">PDF</a>@if ($campaign->status->value === 'closed')<a class="btn btn-outline-dark" href="{{ route('documents.inventory.closure.show', $campaign) }}">PV de clôture</a>@endif @endif @can('manage-inventories') @if ($campaign->status->value === 'draft')<a class="btn btn-primary" href="{{ route('inventories.edit', $campaign) }}">Modifier le brouillon</a>@endif @endcan</div>
    </div>
    @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="alert alert-danger">@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

    @php($controlled = $itemsTotal - $pendingCount)
    @php($progress = $itemsTotal ? (int) round($controlled * 100 / $itemsTotal) : ($campaign->status->value === 'in_progress' ? 100 : 0))
    <section class="card content-card mb-3"><div class="card-body">
        @if (! $isRestrictedAgent)<div class="row g-2 mb-3 small"><div class="col-6 col-lg-3"><strong>{{ $campaign->tasks->count() }}</strong> opérations · <strong>{{ $campaign->tasks->pluck('agent_id')->unique()->count() }}</strong> agents</div><div class="col-6 col-lg-3"><strong>{{ $itemsTotal - $pendingCount }}</strong> biens recensés</div><div class="col-6 col-lg-3"><strong>{{ $pendingCount }}</strong> biens restants</div><div class="col-6 col-lg-3"><strong>{{ $campaign->items()->where(function ($query) { $query->whereNotNull('physical_anomaly')->orWhereNotNull('administrative_anomaly')->orWhereIn('status', ['present_anomaly', 'absent', 'moved', 'damaged', 'unidentified', 'discovered']); })->count() }}</strong> écarts</div></div>@endif
        <div class="d-flex justify-content-between small mb-2"><span>Progression des constats</span><strong>{{ $controlled }} / {{ $itemsTotal }} · {{ $progress }}%</strong></div>
        <div class="progress" role="progressbar" aria-label="Progression de la campagne" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width: {{ $progress }}%"></div></div>
        <div class="d-flex flex-wrap gap-3 mt-3 small text-secondary"><span>À contrôler : {{ $pendingCount }}</span>@foreach ($itemStatusLabels as $value => $label) @if ($value !== 'pending' && ($counts[$value] ?? 0))<span>{{ $label }} : {{ $counts[$value] }}</span>@endif @endforeach</div>
    </div></section>

    @if (! $isRestrictedAgent)<section class="card content-card mb-3"><div class="card-body"><h2 class="h6">Exporter un rapport filtré</h2>
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-12 col-md-2"><label class="form-label small" for="report-direction">Direction</label><select class="form-select form-select-sm" id="report-direction" name="direction_id"><option value="">Toutes</option>@foreach ($directions as $direction)<option value="{{ $direction->id }}">{{ $direction->name }}</option>@endforeach</select></div>
            <div class="col-12 col-md-2"><label class="form-label small" for="report-service">Service</label><select class="form-select form-select-sm" id="report-service" name="service_id"><option value="">Tous</option>@foreach ($services as $service)<option value="{{ $service->id }}">{{ $service->name }}</option>@endforeach</select></div>
            <div class="col-12 col-md-3"><label class="form-label small" for="report-location">Localisation</label><select class="form-select form-select-sm" id="report-location" name="location_id"><option value="">Toutes</option>@foreach ($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
            <div class="col-12 col-md-2"><label class="form-label small" for="report-status">Statut</label><select class="form-select form-select-sm" id="report-status" name="status"><option value="">Tous</option>@foreach ($itemStatusLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
            <div class="col-12 col-md-1"><label class="form-check-label small"><input class="form-check-input" type="checkbox" name="anomalies" value="1"> Écarts</label></div>
            <div class="col-12 col-md-2 d-flex gap-2"><button class="btn btn-sm btn-outline-success" formaction="{{ route('inventories.report.excel', $campaign) }}">Excel</button><button class="btn btn-sm btn-outline-danger" formaction="{{ route('inventories.report.pdf', $campaign) }}">PDF</button></div>
        </form>
    </div></section>@endif

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-5"><section class="card content-card h-100"><div class="card-body p-3 p-md-4">
            <h2 class="h5">Périmètres et opérations</h2>
            @foreach ($campaign->scopes as $scope)
                <article class="border rounded p-3 mb-3">
                    <div class="fw-semibold">{{ $scope->label() }}</div>
                    @if ($scope->task)
                        <div class="small text-secondary mb-2">Agent : {{ $scope->task->agent->first_name }} {{ $scope->task->agent->last_name }}{{ $scope->task->agent->employee_code ? ' · '.$scope->task->agent->employee_code : '' }}{{ $scope->task->agent->service ? ' · '.$scope->task->agent->service->name : '' }}{{ $scope->task->agent->service?->direction ? ' / '.$scope->task->agent->service->direction->name : '' }} · Échéance : {{ $scope->task->due_on?->format('d/m/Y') ?? 'non définie' }} · {{ $scope->task->status === 'submitted' ? 'Soumise' : ($scope->task->status === 'in_progress' ? 'En cours' : 'Attribuée') }}</div>
                        @if ($campaign->status->value === 'in_progress')
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('inventories.items', $campaign) }}?task={{ $scope->task->id }}">Voir les constats</a>
                            @if (auth()->user()->can('manage-inventories') || auth()->user()->agent?->getKey() === $scope->task->agent_id)
                                @if ($scope->task->items()->where('status', 'pending')->doesntExist())
                                    <form method="POST" action="{{ route('inventories.tasks.submit', $scope->task) }}" class="d-inline">@csrf<button class="btn btn-sm btn-primary" type="submit">Soumettre l’opération</button></form>
                                @endif
                            @endif
                        @endif
                    @endif
                    @can('manage-inventories')
                        @if ($campaign->status->value === 'draft')
                            <form method="POST" action="{{ route('inventories.tasks.store', $campaign) }}" class="row g-2 mt-2">@csrf<input type="hidden" name="scope_id" value="{{ $scope->id }}">
                                <div class="col-12"><label class="form-label small" for="agent-search-{{ $scope->id }}">Agent responsable</label><input class="form-control form-control-sm mb-2" type="search" id="agent-search-{{ $scope->id }}" data-agent-search="agent-select-{{ $scope->id }}" placeholder="Rechercher nom, prénom, matricule, service…" autocomplete="off"><select class="form-select form-select-sm" name="agent_id" id="agent-select-{{ $scope->id }}" required><option value="">Choisir un agent actif</option>@foreach ($agents as $agent)@php($agentSearchText = implode(' ', array_filter([$agent->first_name, $agent->last_name, $agent->employee_code, $agent->service?->name, $agent->service?->direction?->name])))<option value="{{ $agent->id }}" data-search="{{ $agentSearchText }}" @selected((string) old('agent_id') === (string) $agent->id || (string) $scope->task?->agent_id === (string) $agent->id)>{{ $agent->first_name }} {{ $agent->last_name }}{{ $agent->employee_code ? ' · '.$agent->employee_code : '' }}{{ $agent->service ? ' · '.$agent->service->name : '' }}{{ $agent->service?->direction ? ' / '.$agent->service->direction->name : '' }}</option>@endforeach</select><div class="form-text">{{ $agents->count() }} agent(s) actif(s) disponible(s).</div></div>
                                <div class="col-7"><label class="form-label small" for="due-{{ $scope->id }}">Échéance</label><input class="form-control form-control-sm" type="date" id="due-{{ $scope->id }}" name="due_on" value="{{ old('due_on', $scope->task?->due_on?->format('Y-m-d')) }}"></div><div class="col-5 d-flex align-items-end"><button class="btn btn-sm btn-primary w-100" type="submit">{{ $scope->task ? 'Modifier l’affectation' : 'Attribuer' }}</button></div>
                            </form>
                        @elseif (! $scope->task)
                            <p class="small text-danger mb-2">Aucun agent attribué.</p>
                        @endif
                    @endcan
                </article>
            @endforeach
            @can('manage-inventories')
                @if ($campaign->status->value === 'draft' && $campaign->scopes->every(fn ($scope) => $scope->task !== null))
                    <form method="POST" action="{{ route('inventories.launch', $campaign) }}" onsubmit="return confirm('Lancer la campagne et figer la liste théorique des biens ?')">@csrf<button class="btn btn-primary" type="submit">Lancer la campagne</button></form>
                @endif
            @endcan
        </div></section></div>

        <div class="col-12 col-xl-7"><section class="card content-card h-100"><div class="card-body p-3 p-md-4">
            <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center"><h2 class="h5 mb-0">Constats et anomalies</h2><a class="btn btn-sm btn-outline-primary" href="{{ route('inventories.items', $campaign) }}">Ouvrir le tableau des constats</a></div>
            <p class="text-secondary small mt-2">Les constats sont séparés des fiches patrimoniales et ne les modifient pas automatiquement.</p>
            @if ($campaign->status->value === 'in_progress')
                <div class="accordion" id="discoverItems">
                    @foreach ($campaign->tasks as $task)
                        <div class="accordion-item"><h3 class="accordion-header"><button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#discover-{{ $task->id }}">Signaler un bien découvert — {{ $task->agent->last_name }} {{ $task->agent->first_name }}</button></h3><div id="discover-{{ $task->id }}" class="accordion-collapse collapse" data-bs-parent="#discoverItems"><div class="accordion-body">
                            <form method="POST" action="{{ route('inventories.discover', $task) }}" class="row g-2">@csrf
                                <div class="col-md-6"><label class="form-label" for="discovered-code-{{ $task->id }}">Référence observée</label><input class="form-control" id="discovered-code-{{ $task->id }}" name="observed_asset_code" maxlength="60"></div>
                                <div class="col-md-6"><label class="form-label" for="discovered-designation-{{ $task->id }}">Désignation observée</label><input class="form-control" id="discovered-designation-{{ $task->id }}" name="observed_designation" maxlength="180"></div>
                                <div class="col-md-6"><label class="form-label" for="discovered-location-{{ $task->id }}">Localisation constatée</label><select class="form-select" id="discovered-location-{{ $task->id }}" name="observed_location_id"><option value="">Non renseignée</option>@foreach ($locations ?? collect() as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
                                <div class="col-md-6"><label class="form-label" for="discovered-status-{{ $task->id }}">Statut du constat</label><select class="form-select" id="discovered-status-{{ $task->id }}" name="status"><option value="discovered">Bien découvert non enregistré</option><option value="unidentified">Non identifié</option></select></div>
                                <div class="col-12"><label class="form-label" for="discovered-notes-{{ $task->id }}">Observations</label><textarea class="form-control" id="discovered-notes-{{ $task->id }}" name="observations" rows="2"></textarea></div>
                                <div class="col-12"><button class="btn btn-sm btn-primary" type="submit">Enregistrer le constat découvert</button></div>
                            </form>
                        </div></div></div>
                    @endforeach
                </div>
            @endif
            @can('verify-inventories')
                @if ($campaign->status->value === 'to_verify')
                    <hr><h3 class="h6">Vérification gestionnaire</h3>
                    <form method="POST" action="{{ route('inventories.verify', $campaign) }}" class="d-flex flex-column flex-md-row gap-2">@csrf<textarea class="form-control" name="observations" rows="2" maxlength="5000" placeholder="Observations de vérification"></textarea><div class="d-flex gap-2"><button class="btn btn-success" name="decision" value="approved" type="submit">Vérification favorable</button><button class="btn btn-outline-danger" name="decision" value="returned" type="submit">Retourner</button></div></form>
                @endif
            @endcan
            @can('validate-inventories')
                @if ($campaign->status->value === 'to_verify' && $campaign->reviews->where('step', 'verification')->last()?->decision === 'approved')
                    <hr><h3 class="h6">Validation administrative</h3><form method="POST" action="{{ route('inventories.validate', $campaign) }}" class="d-flex gap-2">@csrf<input class="form-control" name="observations" maxlength="5000" placeholder="Observations de validation"><button class="btn btn-success" type="submit">Valider la campagne</button></form>
                @endif
            @endcan
            @can('close-inventories')
                @if ($campaign->status->value === 'validated')
                    <hr><form method="POST" action="{{ route('inventories.close', $campaign) }}" onsubmit="return confirm('Clôturer la campagne ? Les constats deviendront non modifiables.')">@csrf<label class="form-label" for="close-notes">Observations de clôture</label><div class="d-flex gap-2"><input class="form-control" id="close-notes" name="observations" maxlength="5000"><button class="btn btn-dark" type="submit">Clôturer</button></div></form>
                @endif
            @endcan
        </div></section></div>
    </div>

    @if (! $isRestrictedAgent)<section class="card content-card"><div class="card-body p-3 p-md-4"><h2 class="h5">Journal de validation et d’activité</h2>
        @forelse ($campaign->reviews as $review)
            <article class="border-start border-3 ps-3 pb-3"><p class="small text-secondary mb-1">{{ $review->reviewed_at->format('d/m/Y H:i') }} · {{ $review->reviewer->name }}</p><p class="mb-1">{{ ucfirst($review->step) }} — {{ $review->decision }}</p>@if ($review->observations)<p class="small mb-0">{{ $review->observations }}</p>@endif</article>
        @empty<p class="text-secondary mb-0">Aucune opération de workflow enregistrée.</p>@endforelse
    </div></section>@endif
@endsection
