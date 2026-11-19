@extends('admin.layout')

@section('title', 'Programmer un entretien')

@section('content')
    <div class="mb-4"><a href="{{ route('maintenance.schedules') }}" class="small">← Programmes préventifs</a><h1 class="h3 mt-2">Programmer un entretien</h1></div>
    @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('maintenance.schedules.store') }}" class="card content-card"><div class="card-body p-4">@csrf<div class="row g-3">
        <div class="col-md-7"><label class="form-label" for="asset_id">Bien patrimonial *</label><select class="form-select" id="asset_id" name="asset_id" required><option value="">Choisir un bien</option>@foreach ($assets as $asset)<option value="{{ $asset->id }}" @selected(old('asset_id') == $asset->id)>{{ $asset->asset_code }} — {{ $asset->designation }}</option>@endforeach</select></div>
        <div class="col-md-5"><label class="form-label" for="title">Nature de l’entretien *</label><input class="form-control" id="title" name="title" maxlength="180" value="{{ old('title') }}" required></div>
        <div class="col-md-4"><label class="form-label" for="frequency">Fréquence *</label><select class="form-select" id="frequency" name="frequency" required>@foreach ($frequencies as $frequency)<option value="{{ $frequency->value }}" @selected(old('frequency') === $frequency->value)>{{ $frequency->label() }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label" for="next_due_on">Première échéance *</label><input class="form-control" type="date" id="next_due_on" name="next_due_on" value="{{ old('next_due_on', today()->format('Y-m-d')) }}" required></div>
        <div class="col-md-4"><label class="form-label" for="assigned_agent_id">Responsable ARTF</label><select class="form-select" id="assigned_agent_id" name="assigned_agent_id"><option value="">À désigner / prestataire</option>@foreach ($agents as $agent)<option value="{{ $agent->id }}">{{ $agent->last_name }} {{ $agent->first_name }} · {{ $agent->service?->name ?? 'Sans service' }}</option>@endforeach</select></div>
        <div class="col-md-6"><label class="form-label" for="provider_name">Prestataire externe</label><input class="form-control" id="provider_name" name="provider_name" maxlength="180" value="{{ old('provider_name') }}"></div>
        <div class="col-12"><label class="form-label" for="description">Description / consignes</label><textarea class="form-control" id="description" name="description" rows="4">{{ old('description') }}</textarea></div>
    </div><div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-outline-secondary" href="{{ route('maintenance.schedules') }}">Annuler</a><button class="btn btn-primary">Enregistrer le programme</button></div></div></form>
@endsection
