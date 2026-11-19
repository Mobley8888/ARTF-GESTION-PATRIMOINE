@extends('admin.layout')

@section('title', 'Compte utilisateur')

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <a class="small text-decoration-none" href="{{ route('users.index') }}"><i class="bi bi-arrow-left me-1"></i>Retour aux utilisateurs</a>
            <p class="text-primary text-uppercase fw-semibold small mt-3 mb-1">Compte utilisateur</p>
            <h1 class="h3 mb-1">{{ $user->name }}</h1>
            <p class="text-secondary mb-0">{{ $user->email }}</p>
        </div>
        <a class="btn btn-primary flex-shrink-0" href="{{ route('users.edit', $user) }}">
            <i class="bi bi-pencil me-1"></i> Gérer le compte
        </a>
    </div>

    <section class="card content-card">
        <div class="card-body p-3 p-md-4">
            <h2 class="h5 mb-3">Informations du compte</h2>
            <dl class="row mb-0">
                <dt class="col-sm-4 text-secondary fw-normal">Nom complet</dt>
                <dd class="col-sm-8">{{ $user->name }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Adresse e-mail</dt>
                <dd class="col-sm-8">{{ $user->email }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Rôle</dt>
                <dd class="col-sm-8">{{ $user->role->label() }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">État du compte</dt>
                <dd class="col-sm-8">
                    <span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                        {{ $user->is_active ? 'Actif' : 'Désactivé' }}
                    </span>
                </dd>
                <dt class="col-sm-4 text-secondary fw-normal">Créé le</dt>
                <dd class="col-sm-8">{{ $user->created_at->format('d/m/Y H:i') }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Dernière connexion</dt>
                <dd class="col-sm-8">{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Jamais' }}</dd>
                <dt class="col-sm-4 text-secondary fw-normal">Changement de mot de passe</dt>
                <dd class="col-sm-8">{{ $user->must_change_password ? 'Requis à la prochaine connexion' : 'Non requis' }}</dd>
            </dl>
        </div>
    </section>
@endsection
