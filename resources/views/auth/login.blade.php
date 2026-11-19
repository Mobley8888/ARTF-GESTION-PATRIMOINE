<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion - ARTF Patrimoine</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <main class="container min-vh-100 d-flex align-items-center justify-content-center py-5">
        <section class="card content-card shadow-sm w-100" style="max-width: 440px;">
            <div class="card-body p-4 p-md-5">
                <a class="sidebar-brand d-flex text-decoration-none p-0 border-0 mb-4" href="{{ route('login') }}">
                    <span class="brand-mark"><i class="bi bi-buildings"></i></span>
                    <span>ARTF <span class="text-secondary fw-normal">Patrimoine</span></span>
                </a>
                <h1 class="h4 mb-2">Connexion</h1>
                <p class="text-secondary mb-4">Connectez-vous avec votre adresse e-mail et votre mot de passe.</p>

                @if (session('status'))
                    <div class="alert alert-success">{{ session('status') }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="email">Adresse e-mail</label>
                        <input
                            class="form-control @error('email') is-invalid @enderror"
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="username"
                            required
                            autofocus
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
                            autocomplete="current-password"
                            required
                        >
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-check mb-4">
                        <input class="form-check-input" id="remember" name="remember" type="checkbox" value="1">
                        <label class="form-check-label" for="remember">Se souvenir de moi</label>
                    </div>
                    <button class="btn btn-primary w-100" type="submit">Se connecter</button>
                </form>

                @if (config('registration.public_enabled'))
                    <p class="text-center text-secondary mt-4 mb-0">
                        Vous n’avez pas encore de compte ?
                        <a href="{{ route('register') }}">Créer un compte</a>
                    </p>
                @endif
            </div>
        </section>
    </main>
</body>
</html>
