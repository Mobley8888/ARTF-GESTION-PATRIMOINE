<form method="POST" action="{{ $user ? route('users.update', $user) : route('users.store') }}">
    @csrf
    @if ($user)
        @method('PUT')
    @endif
    <div class="row g-3">
        <div class="col-12">
            <label class="form-label" for="name">Nom complet <span class="text-danger">*</span></label>
            <input class="form-control @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $user?->name) }}" maxlength="255" required>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="email">Adresse e-mail <span class="text-danger">*</span></label>
            <input class="form-control @error('email') is-invalid @enderror" id="email" name="email" type="email" value="{{ old('email', $user?->email) }}" maxlength="255" required>
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-7">
            <label class="form-label" for="role">Rôle <span class="text-danger">*</span></label>
            <select class="form-select @error('role') is-invalid @enderror" id="role" name="role" required>
                @foreach ($roles as $value => $label)
                    <option value="{{ $value }}" @selected(old('role', $user?->role?->value ?? 'viewer') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-12 col-md-5">
            <label class="form-label" for="is_active">État du compte</label>
            <select class="form-select @error('is_active') is-invalid @enderror" id="is_active" name="is_active" required>
                <option value="1" @selected((string) old('is_active', $user ? (int) $user->is_active : 1) === '1')>Actif</option>
                <option value="0" @selected((string) old('is_active', $user ? (int) $user->is_active : 1) === '0')>Désactivé</option>
            </select>
            @error('is_active')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        @unless ($user)
            <div class="col-12">
                <label class="form-label" for="password">Mot de passe temporaire <span class="text-danger">*</span></label>
                <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">12 caractères minimum; le titulaire devra le changer dès sa première connexion.</div>
            </div>
            <div class="col-12">
                <label class="form-label" for="password_confirmation">Confirmer le mot de passe temporaire <span class="text-danger">*</span></label>
                <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
            </div>
        @endunless
        <div class="col-12 pt-2">
            <button class="btn btn-primary" type="submit">{{ $user ? 'Enregistrer' : 'Créer le compte' }}</button>
        </div>
    </div>
</form>
