<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Changer le mot de passe - ARTF Patrimoine</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="container min-vh-100 d-flex align-items-center justify-content-center py-5">
        <section class="card content-card shadow-sm w-100" style="max-width: 480px;">
            <div class="card-body p-4 p-md-5">
                <h1 class="h4 mb-2">Changer votre mot de passe</h1>
                <p class="text-secondary mb-4">Un nouveau mot de passe est requis avant de poursuivre.</p>
                <form method="POST" action="{{ route('password.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Mot de passe actuel</label>
                        <input class="form-control @error('current_password') is-invalid @enderror" id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                        @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Nouveau mot de passe</label>
                        <input class="form-control @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password" minlength="12" required>
                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">12 caractères minimum.</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password_confirmation">Confirmer le nouveau mot de passe</label>
                        <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Changer le mot de passe</button>
                </form>
                <form class="mt-3" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-outline-secondary w-100" type="submit">Déconnexion</button>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
