@php($selectedType = old('type', $location?->type?->value ?? 'site'))

<form method="POST" action="{{ $location ? route('references.locations.update', $location) : route('references.locations.store') }}" data-location-admin-form>
    @csrf
    @if ($location)
        @method('PUT')
    @endif
    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label class="form-label" for="type">Niveau <span class="text-danger">*</span></label>
            <select class="form-select @error('type') is-invalid @enderror" id="type" name="type" data-location-admin-type required>
                @foreach ($typeOptions as $value => $label)
                    <option value="{{ $value }}" @selected($selectedType === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="parent_id">Lieu parent</label>
            <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id" data-location-admin-parent>
                <option value="">Aucun (site)</option>
                @foreach ($parents as $parent)
                    @php($parentLabel = collect([$parent->parent?->name, $parent->name])->filter()->implode(' / '))
                    <option value="{{ $parent->id }}" data-type="{{ $parent->type->value }}" @selected((string) old('parent_id', $location?->parent_id) === (string) $parent->id)>{{ $parentLabel }} ({{ $parent->code }})</option>
                @endforeach
            </select>
            @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Un bâtiment dépend d’un site. Un local dépend du site ou d’un bâtiment, selon l’organisation du lieu.</div>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="code">Code <span class="text-danger">*</span></label>
            <input class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $location?->code) }}" maxlength="40" required>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-8">
            <label class="form-label" for="name">Désignation <span class="text-danger">*</span></label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $location?->name) }}" maxlength="160" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-8">
            <label class="form-label" for="address">Adresse</label>
            <input class="form-control @error('address') is-invalid @enderror" id="address" name="address" value="{{ old('address', $location?->address) }}" maxlength="255">
            @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="surface_m2">Superficie (m²)</label>
            <input class="form-control @error('surface_m2') is-invalid @enderror" id="surface_m2" name="surface_m2" type="number" min="0" max="9999999999.99" step="0.01" value="{{ old('surface_m2', $location?->surface_m2) }}">
            @error('surface_m2')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $location?->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 d-flex flex-column flex-sm-row gap-2 pt-2">
            <button class="btn btn-primary" type="submit">{{ $location ? 'Enregistrer les modifications' : 'Créer le lieu' }}</button>
            <a class="btn btn-outline-secondary" href="{{ $location ? route('references.locations.show', $location) : route('references.locations.index') }}">Annuler</a>
        </div>
    </div>
</form>
