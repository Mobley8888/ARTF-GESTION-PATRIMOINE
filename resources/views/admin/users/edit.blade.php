@extends('admin.layout')

@section('title', 'Modifier un utilisateur')

@section('content')
    <div class="mb-4">
        <a class="small text-decoration-none" href="{{ route('users.index') }}"><i class="bi bi-arrow-left me-1"></i>Retour aux utilisateurs</a>
        <h1 class="h3 mt-2 mb-1">{{ $user->name }}</h1>
        <p class="text-secondary mb-0">{{ $user->email }}</p>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <section class="card content-card h-100">
                <div class="card-body p-3 p-md-4">
                    <h2 class="h5 mb-3">Informations et habilitations</h2>
                    @include('admin.users._form')
                </div>
            </section>
        </div>
        <div class="col-12 col-xl-5">
            <section class="card content-card h-100">
                <div class="card-body p-3 p-md-4">
                    <h2 class="h5 mb-2">Réinitialiser le mot de passe</h2>
                    <p class="small text-secondary">Le nouveau mot de passe est saisi par l’administrateur et devra être changé à la prochaine connexion.</p>
                    @include('admin.users._password-form')
                </div>
            </section>
        </div>
    </div>
@endsection
