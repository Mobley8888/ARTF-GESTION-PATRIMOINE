<form method="POST" action="{{ $category ? route('references.categories.update', $category) : route('references.categories.store') }}">
    @csrf
    @if ($category)
        @method('PUT')
    @endif
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label" for="parent_id">Catégorie parente</label>
            <select class="form-select @error('parent_id') is-invalid @enderror" id="parent_id" name="parent_id">
                <option value="">Catégorie principale</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected((string) old('parent_id', $category?->parent_id) === (string) $parent->id)>{{ $parent->name }} ({{ $parent->code }})</option>
                @endforeach
            </select>
            @error('parent_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">Les sous-catégories sont limitées à un seul niveau sous une catégorie principale.</div>
        </div>
        <div class="col-12 col-md-4">
            <label class="form-label" for="code">Code <span class="text-danger">*</span></label>
            <input class="form-control @error('code') is-invalid @enderror" id="code" name="code" value="{{ old('code', $category?->code) }}" maxlength="40" required>
            @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-8">
            <label class="form-label" for="name">Désignation <span class="text-danger">*</span></label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $category?->name) }}" maxlength="120" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="description">Description</label>
            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3">{{ old('description', $category?->description) }}</textarea>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 d-flex flex-column flex-sm-row gap-2 pt-2">
            <button class="btn btn-primary" type="submit">{{ $category ? 'Enregistrer les modifications' : 'Créer la catégorie' }}</button>
            <a class="btn btn-outline-secondary" href="{{ $category ? route('references.categories.show', $category) : route('references.categories.index') }}">Annuler</a>
        </div>
    </div>
</form>
