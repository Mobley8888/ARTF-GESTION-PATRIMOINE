@extends('admin.layout')

@section('title', isset($campaign) ? 'Modifier la campagne' : 'Nouvelle campagne d’inventaire')

@section('content')
    @php($campaign = $campaign ?? null)
    @php($selectedDirections = old('direction_ids', $campaign?->scopes->pluck('direction_id')->filter()->all() ?? []))
    @php($selectedServices = old('service_ids', $campaign?->scopes->pluck('service_id')->filter()->all() ?? []))
    @php($selectedLocations = old('location_ids', $campaign?->scopes->pluck('location_id')->filter()->all() ?? []))
    @php($globalSelected = old('global_scope', $campaign?->scopes->contains('scope_type', 'global') ?? false))
    <div class="mb-4"><a class="small text-decoration-none" href="{{ route('inventories.index') }}">Retour aux campagnes</a><h1 class="h3 mt-2">{{ $campaign ? 'Modifier la campagne d’inventaire' : 'Créer une campagne d’inventaire' }}</h1><p class="text-secondary">Une campagne peut couvrir plusieurs directions, services et localisations. Les recouvrements seront dédupliqués au lancement.</p></div>
    @if ($errors->any())<div class="alert alert-danger"><strong>Vérifiez les informations de la campagne.</strong><ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if (session()->has('inventory_preview_count'))<div class="alert alert-info" role="status">Périmètre estimé : <strong>{{ session('inventory_preview_count') }}</strong> bien(s) actuellement concernés. Le total définitif sera figé au lancement.</div>@endif

    @if ($campaign && $scopesLocked)<div class="alert alert-info">Les opérations ont déjà été attribuées : le périmètre est verrouillé. Vous pouvez encore modifier les informations et les dates du brouillon.</div>@endif
    <form method="POST" action="{{ $campaign ? route('inventories.update', $campaign) : route('inventories.store') }}">
        @csrf
        @if ($campaign) @method('PUT') @endif
        <section class="card content-card mb-3"><div class="card-body p-3 p-md-4">
            <h2 class="h5 mb-3">Informations de la campagne</h2>
            <div class="row g-3">
                <div class="col-12 col-md-4"><label class="form-label" for="reference">Référence <span class="text-danger">*</span></label><input class="form-control" id="reference" name="reference" value="{{ old('reference', $campaign?->reference) }}" maxlength="60" required></div>
                <div class="col-12 col-md-8"><label class="form-label" for="title">Intitulé <span class="text-danger">*</span></label><input class="form-control" id="title" name="title" value="{{ old('title', $campaign?->title) }}" maxlength="180" required></div>
                <div class="col-12 col-md-6"><label class="form-label" for="starts_on">Date de début</label><input class="form-control" type="date" id="starts_on" name="starts_on" value="{{ old('starts_on', $campaign?->starts_on?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required></div>
                <div class="col-12 col-md-6"><label class="form-label" for="ends_on">Date de fin prévue</label><input class="form-control" type="date" id="ends_on" name="ends_on" value="{{ old('ends_on', $campaign?->ends_on?->format('Y-m-d') ?? now()->addMonth()->format('Y-m-d')) }}" required></div>
                <div class="col-12"><label class="form-label" for="description">Description et consignes</label><textarea class="form-control" id="description" name="description" rows="3" maxlength="5000">{{ old('description', $campaign?->description) }}</textarea></div>
            </div>
        </div></section>

        <section class="card content-card mb-3"><div class="card-body p-3 p-md-4">
            <h2 class="h5 mb-2">Périmètre</h2><p class="small text-secondary">Cochez le périmètre global ou sélectionnez une ou plusieurs cibles. Les biens appartenant à plusieurs périmètres ne seront comptés qu’une fois.</p>
            @if ($scopesLocked ?? false)
                @if ($globalSelected)<input type="hidden" name="global_scope" value="1">@endif
                @foreach ($selectedDirections as $id)<input type="hidden" name="direction_ids[]" value="{{ $id }}">@endforeach
                @foreach ($selectedServices as $id)<input type="hidden" name="service_ids[]" value="{{ $id }}">@endforeach
                @foreach ($selectedLocations as $id)<input type="hidden" name="location_ids[]" value="{{ $id }}">@endforeach
            @endif
            <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="global_scope" name="global_scope" value="1" @checked($globalSelected) @disabled($scopesLocked ?? false)><label class="form-check-label fw-semibold" for="global_scope">Inventaire global du patrimoine</label></div>
            <div class="row g-3">
                <div class="col-12 col-lg-4"><h3 class="h6">Directions</h3><div class="border rounded p-2 inventory-scope-list">@forelse ($directions as $direction)<label class="d-flex gap-2 py-1"><input type="checkbox" name="direction_ids[]" value="{{ $direction->id }}" @checked(in_array($direction->id, $selectedDirections)) @disabled($scopesLocked ?? false)><span>{{ $direction->name }}</span></label>@empty<p class="small text-secondary mb-0">Aucune direction active.</p>@endforelse</div></div>
                <div class="col-12 col-lg-4"><h3 class="h6">Services</h3><div class="border rounded p-2 inventory-scope-list">@forelse ($services as $service)<label class="d-flex gap-2 py-1"><input type="checkbox" name="service_ids[]" value="{{ $service->id }}" @checked(in_array($service->id, $selectedServices)) @disabled($scopesLocked ?? false)><span>{{ $service->name }} <span class="text-secondary">· {{ $service->direction->name }}</span></span></label>@empty<p class="small text-secondary mb-0">Aucun service actif.</p>@endforelse</div></div>
                <div class="col-12 col-lg-4"><h3 class="h6">Sites, bâtiments et locaux</h3><div class="border rounded p-2 inventory-scope-list">@forelse ($locations as $location)<label class="d-flex gap-2 py-1"><input type="checkbox" name="location_ids[]" value="{{ $location->id }}" @checked(in_array($location->id, $selectedLocations)) @disabled($scopesLocked ?? false)><span>{{ collect([$location->parent?->parent?->name, $location->parent?->name, $location->name])->filter()->implode(' / ') }}</span></label>@empty<p class="small text-secondary mb-0">Aucune localisation active.</p>@endforelse</div></div>
            </div>
        </div></section>
        <div class="d-flex flex-wrap gap-2">@if (! $campaign)<button class="btn btn-outline-primary" type="submit" formaction="{{ route('inventories.preview') }}" formmethod="POST">Estimer le nombre de biens</button>@endif<button class="btn btn-primary" type="submit">{{ $campaign ? 'Enregistrer les modifications' : 'Créer le brouillon' }}</button><a class="btn btn-outline-secondary" href="{{ $campaign ? route('inventories.show', $campaign) : route('inventories.index') }}">Annuler</a></div>
    </form>
@endsection
