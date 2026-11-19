<form method="POST" action="{{ route('users.password', $user) }}">
    @csrf
    @method('PUT')
    <div class="mb-3">
        <label class="form-label" for="reset_password">Nouveau mot de passe temporaire</label>
        <input class="form-control @error('password') is-invalid @enderror" id="reset_password" name="password" type="password" autocomplete="new-password" minlength="12" required>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="mb-3">
        <label class="form-label" for="reset_password_confirmation">Confirmer le mot de passe</label>
        <input class="form-control" id="reset_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
    </div>
    <button class="btn btn-outline-primary" type="submit">Réinitialiser</button>
</form>
