@extends('admin.layout')

@section('title', 'Nouvel utilisateur')

@section('content')
    <div class="mb-4">
        <a class="small text-decoration-none" href="{{ route('users.index') }}"><i class="bi bi-arrow-left me-1"></i>Retour aux utilisateurs</a>
        <h1 class="h3 mt-2 mb-1">Créer un utilisateur</h1>
        <p class="text-secondary mb-0">Le nouvel utilisateur devra changer son mot de passe à la première connexion.</p>
    </div>
    <section class="card content-card">
        <div class="card-body p-3 p-md-4">
            @include('admin.users._form')
        </div>
    </section>
@endsection
