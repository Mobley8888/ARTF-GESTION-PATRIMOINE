@extends('admin.layout')

@section('title', 'Déclarer une panne')

@section('content')
    <div class="mb-4"><a href="{{ route('maintenance.index') }}" class="small">← Maintenance</a><h1 class="h3 mt-2">Déclarer une panne</h1><p class="text-secondary">La situation patrimoniale du bien ne sera pas modifiée automatiquement.</p></div>
    @if ($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('maintenance.requests.store') }}" class="card content-card"><div class="card-body p-4">@csrf
        <div class="row g-3"><div class="col-md-6"><label for="asset_id" class="form-label">Bien concerné *</label><select class="form-select" id="asset_id" name="asset_id" required><option value="">Choisir un bien</option>@foreach ($assets as $asset)<option value="{{ $asset->id }}" @selected(old('asset_id', $selectedAsset) == $asset->id)>{{ $asset->asset_code }} — {{ $asset->designation }}</option>@endforeach</select>@error('asset_id')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        <div class="col-md-3"><label for="priority" class="form-label">Priorité *</label><select class="form-select" id="priority" name="priority" required>@foreach (['low' => 'Faible', 'normal' => 'Normale', 'high' => 'Élevée', 'urgent' => 'Urgente'] as $value => $label)<option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="col-12"><label for="title" class="form-label">Objet / panne constatée *</label><input class="form-control" id="title" name="title" maxlength="180" value="{{ old('title') }}" required></div>
        <div class="col-12"><label for="description" class="form-label">Description détaillée *</label><textarea class="form-control" id="description" name="description" rows="5" required>{{ old('description') }}</textarea></div></div>
        <div class="d-flex justify-content-end gap-2 mt-4"><a class="btn btn-outline-secondary" href="{{ route('maintenance.index') }}">Annuler</a><button class="btn btn-primary">Enregistrer le signalement</button></div>
    </div></form>
@endsection
