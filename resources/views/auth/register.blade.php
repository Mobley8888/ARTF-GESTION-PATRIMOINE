<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Créer un compte - ARTF Patrimoine</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="container min-vh-100 d-flex align-items-center justify-content-center py-5">
        <section class="card content-card shadow-sm w-100" style="max-width: 500px;">
            <div class="card-body p-4 p-md-5">
                <a class="sidebar-brand d-flex text-decoration-none p-0 border-0 mb-4" href="{{ route('login') }}">
                    <span class="brand-mark"><i class="bi bi-buildings"></i></span>
                    <span>ARTF <span class="text-secondary fw-normal">Patrimoine</span></span>
                </a>
                <h1 class="h4 mb-2">Créer un compte</h1>
                <p class="text-secondary mb-4">Remplissez les informations ci-dessous pour créer votre compte de consultation.</p>

                <form method="POST" action="{{ route('register.store') }}">
                    @csrf
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="first_name">Prénom</label>
                            <input
                                class="form-control @error('first_name') is-invalid @enderror"
                                id="first_name"
                                name="first_name"
                                type="text"
                                value="{{ old('first_name') }}"
                                maxlength="120"
                                autocomplete="given-name"
                                required
                                autofocus
                            >
                            @error('first_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label" for="last_name">Nom</label>
                            <input
                                class="form-control @error('last_name') is-invalid @enderror"
                                id="last_name"
                                name="last_name"
                                type="text"
                                value="{{ old('last_name') }}"
                                maxlength="120"
                                autocomplete="family-name"
                                required
                            >
                            @error('last_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Adresse e-mail professionnelle</label>
                        <input
                            class="form-control @error('email') is-invalid @enderror"
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            maxlength="255"
                            autocomplete="work email"
                            required
                        >
                        @error('email')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Mot de passe</label>
                        <input
                            class="form-control @error('password') is-invalid @enderror"
                            id="password"
                            name="password"
                            type="password"
                            minlength="12"
                            autocomplete="new-password"
                            required
                        >
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="password_confirmation">Confirmer le mot de passe</label>
                        <input
                            class="form-control"
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            minlength="12"
                            autocomplete="new-password"
                            required
                        >
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Créer mon compte</button>
                </form>

                <p class="text-center text-secondary mt-4 mb-0">
                    Vous avez déjà un compte ?
                    <a href="{{ route('login') }}">Se connecter</a>
                </p>
            </div>
        </section>
    </main>
</body>
</html>
