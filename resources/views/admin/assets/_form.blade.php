@php
    $editing = $asset !== null;
    $characteristics = old('characteristics', $asset?->characteristics ?? []);
    $characteristicsJson = is_string($characteristics)
        ? $characteristics
        : json_encode((object) ($characteristics ?? []), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $selectedCategoryId = old('category_id', $asset?->category_id);
    $selectedCategory = $categories->first(fn ($category) => (string) $category->id === (string) $selectedCategoryId);
    $defaultCategoryParentId = $selectedCategory?->parent_id ?? $selectedCategory?->id;
    $defaultSubcategoryId = $selectedCategory?->parent_id !== null ? $selectedCategory->id : null;
    $selectedCategoryParentId = old('category_parent_id', $defaultCategoryParentId);
    $selectedSubcategoryId = old('subcategory_id', $defaultSubcategoryId);
    $selectedLocationId = old('location_id', $asset?->location_id);
    $selectedLocation = $locations->first(fn ($location) => (string) $location->id === (string) $selectedLocationId);
    $defaultSiteId = match (true) {
        $selectedLocation?->type?->isSite() => $selectedLocation->id,
        $selectedLocation?->type?->isBuilding() => $selectedLocation->parent_id,
        $selectedLocation?->type?->isLocal() => $selectedLocation->parent?->type?->isSite()
            ? $selectedLocation->parent_id
            : $selectedLocation->parent?->parent_id,
        default => null,
    };
    $defaultBuildingId = match (true) {
        $selectedLocation?->type?->isBuilding() => $selectedLocation->id,
        $selectedLocation?->type?->isLocal() && $selectedLocation->parent?->type?->isBuilding() => $selectedLocation->parent_id,
        default => null,
    };
    $selectedSiteId = old('site_id', $defaultSiteId);
    $selectedBuildingId = old('building_id', $defaultBuildingId);
    $selectedRoomId = old('room_id', $selectedLocation?->type?->isLocal() ? $selectedLocation->id : null);
@endphp

<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('assets.update', $asset) : route('assets.store') }}">
    @csrf
    @if ($editing)
        @method('PUT')
    @endif

    <div class="row g-3">
        <div class="col-12 col-md-6">
            <label class="form-label" for="asset_code">Code du bien <span class="text-danger">*</span></label>
            <input class="form-control @error('asset_code') is-invalid @enderror" id="asset_code" name="asset_code" value="{{ old('asset_code', $asset?->asset_code) }}" maxlength="60" required>
            @error('asset_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="designation">Désignation <span class="text-danger">*</span></label>
            <input class="form-control @error('designation') is-invalid @enderror" id="designation" name="designation" value="{{ old('designation', $asset?->designation) }}" maxlength="180" required>
            @error('designation')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12" data-category-cascade>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="category_parent_id">Catégorie principale <span class="text-danger">*</span></label>
                    <select class="form-select @error('category_parent_id') is-invalid @enderror @error('category_id') is-invalid @enderror" id="category_parent_id" name="category_parent_id" data-category-parent required>
                        <option value="">Sélectionner une catégorie principale</option>
                        @foreach ($categories->whereNull('parent_id') as $category)
                            <option value="{{ $category->id }}" @selected((string) $selectedCategoryParentId === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('category_parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="subcategory_id">Sous-catégorie</label>
                    <select class="form-select @error('subcategory_id') is-invalid @enderror" id="subcategory_id" name="subcategory_id" data-category-child>
                        <option value="">Aucune sous-catégorie</option>
                        @foreach ($categories->whereNotNull('parent_id') as $subcategory)
                            <option value="{{ $subcategory->id }}" data-parent-category="{{ $subcategory->parent_id }}" @selected((string) $selectedSubcategoryId === (string) $subcategory->id)>{{ $subcategory->name }}</option>
                        @endforeach
                    </select>
                    @error('subcategory_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">La sous-catégorie dépend de la catégorie principale sélectionnée.</div>
                </div>
            </div>
        </div>
        <div class="col-12" data-location-cascade>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <label class="form-label" for="site_id">Site</label>
                    <select class="form-select" id="site_id" name="site_id" data-location-site>
                        <option value="">Non renseigné</option>
                        @foreach ($locations as $location)
                            @if ($location->type->isSite())
                                <option value="{{ $location->id }}" @selected((string) $selectedSiteId === (string) $location->id)>{{ $location->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="building_id">Bâtiment</label>
                    <select class="form-select" id="building_id" name="building_id" data-location-building>
                        <option value="">Non renseigné</option>
                        @foreach ($locations as $location)
                            @if ($location->type->isBuilding())
                                <option value="{{ $location->id }}" data-parent="{{ $location->parent_id }}" @selected((string) $selectedBuildingId === (string) $location->id)>{{ $location->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label" for="room_id">Local physique (bureau, salle, magasin…)</label>
                    <select class="form-select" id="room_id" name="room_id" data-location-room>
                        <option value="">Non renseigné</option>
                        @foreach ($locations as $location)
                            @if ($location->type->isLocal())
                                <option value="{{ $location->id }}" data-parent="{{ $location->parent_id }}" @selected((string) $selectedRoomId === (string) $location->id)>{{ $location->name }}</option>
                            @endif
                        @endforeach
                    </select>
                </div>
            </div>
            <input id="location_id" name="location_id" type="hidden" value="{{ old('location_id', $selectedLocationId) }}" data-location-value>
            @error('location_id')<div class="text-danger small mt-1" role="alert">{{ $message }}</div>@enderror
            @error('site_id')<div class="text-danger small mt-1" role="alert">{{ $message }}</div>@enderror
            @error('building_id')<div class="text-danger small mt-1" role="alert">{{ $message }}</div>@enderror
            @error('room_id')<div class="text-danger small mt-1" role="alert">{{ $message }}</div>@enderror
            <div class="form-text">Emplacement physique du bien, distinct du service ou de l’affectation à un agent. Un local peut dépendre directement du site ou d’un bâtiment; chaque changement est ajouté à l’historique.</div>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="serial_number">Numéro de série</label>
            <input class="form-control @error('serial_number') is-invalid @enderror" id="serial_number" name="serial_number" value="{{ old('serial_number', $asset?->serial_number) }}" maxlength="120">
            @error('serial_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12"><h2 class="h6 border-bottom pb-2 mt-3">Caractéristiques techniques</h2></div>
        @foreach (['manufacturer' => 'Fabricant / constructeur', 'brand' => 'Marque', 'model' => 'Modèle', 'manufacturer_reference' => 'Référence constructeur'] as $field => $label)
            <div class="col-12 col-md-6">
                <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                <input class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $asset?->{$field}) }}" maxlength="160">
                @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endforeach
        <div class="col-12"><h2 class="h6 border-bottom pb-2 mt-3">Acquisition</h2></div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="acquired_on">Date d’acquisition</label>
            <input class="form-control @error('acquired_on') is-invalid @enderror" id="acquired_on" name="acquired_on" type="date" value="{{ old('acquired_on', $asset?->acquired_on?->format('Y-m-d')) }}">
            @error('acquired_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="acquisition_value">Valeur d’acquisition (FCFA)</label>
            <input class="form-control @error('acquisition_value') is-invalid @enderror" id="acquisition_value" name="acquisition_value" type="number" min="0" max="9999999999999999.99" step="0.01" value="{{ old('acquisition_value', $asset?->acquisition_value) }}">
            @error('acquisition_value')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Aucun calcul d’amortissement n’est effectué.</div>
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="commissioned_on">Date de mise en service</label>
            <input class="form-control @error('commissioned_on') is-invalid @enderror" id="commissioned_on" name="commissioned_on" type="date" value="{{ old('commissioned_on', $asset?->commissioned_on?->format('Y-m-d')) }}">
            @error('commissioned_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        @foreach (['acquisition_mode' => 'Mode d’acquisition', 'supplier' => 'Fournisseur / provenance', 'invoice_reference' => 'Référence facture', 'delivery_reference' => 'Référence bon de livraison / réception'] as $field => $label)
            <div class="col-12 col-md-6">
                <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                <input class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $asset?->{$field}) }}" maxlength="180">
                @error($field)<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        @endforeach
        <div class="col-12">
            <label class="form-label" for="acquisition_notes">Observations relatives à l’acquisition</label>
            <textarea class="form-control @error('acquisition_notes') is-invalid @enderror" id="acquisition_notes" name="acquisition_notes" rows="2">{{ old('acquisition_notes', $asset?->acquisition_notes) }}</textarea>
            @error('acquisition_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12"><h2 class="h6 border-bottom pb-2 mt-3">État du bien</h2></div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="condition">État physique</label>
            <select class="form-select @error('condition') is-invalid @enderror" id="condition" name="condition">
                <option value="">Non renseigné</option>
                @foreach ($conditionLabels as $value => $label)
                    <option value="{{ $value }}" @selected(old('condition', $asset?->condition?->value) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('condition')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="status">Statut <span class="text-danger">*</span></label>
            <select class="form-select @error('status') is-invalid @enderror" id="status" name="status" required>
                @foreach ($statusLabels as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $asset?->status?->value ?? 'in_service') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-6">
            <label class="form-label" for="usage_status">Situation d’utilisation</label>
            <select class="form-select @error('usage_status') is-invalid @enderror" id="usage_status" name="usage_status">
                <option value="">Non renseignée</option>
                @foreach (['in_service' => 'En service', 'broken' => 'En panne', 'under_repair' => 'En réparation', 'retired' => 'Réformé', 'other' => 'Autre'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('usage_status', $asset?->usage_status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('usage_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $asset?->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="characteristics">Caractéristiques (objet JSON)</label>
            <textarea class="form-control font-monospace @error('characteristics') is-invalid @enderror" id="characteristics" name="characteristics" rows="5" placeholder='{"clé":"valeur"}'>{{ $characteristicsJson }}</textarea>
            @error('characteristics')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Saisir un objet JSON valide, sans donnée personnelle.</div>
        </div>
        <div class="col-12 d-flex flex-column flex-sm-row gap-2 pt-2">
            <button class="btn btn-primary" type="submit" @disabled(! $editing && $categories->isEmpty())>{{ $editing ? 'Enregistrer les modifications' : 'Créer le bien' }}</button>
            <a class="btn btn-outline-secondary" href="{{ $editing ? route('assets.show', $asset) : route('assets.index') }}">Annuler</a>
        </div>
    </div>
</form>
