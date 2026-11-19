@extends('admin.layout')

@section('title', 'Utilisateurs')

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <p class="text-primary text-uppercase fw-semibold small mb-1">Administration</p>
            <h1 class="h3 mb-1">Utilisateurs et habilitations</h1>
            <p class="text-secondary mb-0">{{ $users->total() }} compte(s)</p>
        </div>
        <a class="btn btn-primary flex-shrink-0" href="{{ route('users.create') }}"><i class="bi bi-person-plus me-1"></i> Nouvel utilisateur</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <section class="card content-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('users.index') }}">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-5">
                        <label class="form-label" for="q">Recherche</label>
                        <input class="form-control" id="q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Nom ou adresse e-mail">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label" for="role">Rôle</label>
                        <select class="form-select" id="role" name="role">
                            <option value="">Tous</option>
                            @foreach ($roles as $value => $label)
                                <option value="{{ $value }}" @selected(($filters['role'] ?? '') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2">
                        <label class="form-label" for="active">État du compte</label>
                        <select class="form-select" id="active" name="active">
                            <option value="">Tous</option>
                            <option value="active" @selected(($filters['active'] ?? '') === 'active')>Actif</option>
                            <option value="inactive" @selected(($filters['active'] ?? '') === 'inactive')>Désactivé</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex gap-2">
                        <button class="btn btn-primary" type="submit">Filtrer</button>
                        <a class="btn btn-outline-secondary" href="{{ route('users.index') }}">Effacer</a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <section class="card content-card user-table-card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Utilisateur</th>
                        <th>Rôle</th>
                        <th>État</th>
                        <th>Créé le</th>
                        <th>Dernière connexion</th>
                        <th><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td><div class="fw-semibold">{{ $user->name }}</div><div class="small text-secondary">{{ $user->email }}</div></td>
                            <td>{{ $roles[$user->role->value] }}</td>
                            <td><span class="badge {{ $user->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $user->is_active ? 'Actif' : 'Désactivé' }}</span></td>
                            <td class="text-nowrap">{{ $user->created_at->format('d/m/Y') }}</td>
                            <td class="text-nowrap">{{ $user->last_login_at?->format('d/m/Y H:i') ?? 'Jamais' }}</td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('users.show', $user) }}">Consulter</a>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('users.edit', $user) }}">Gérer</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td class="text-center text-secondary py-5" colspan="6">Aucun utilisateur ne correspond à ces critères.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($users->hasPages())
            <div class="card-body border-top">{{ $users->links() }}</div>
        @endif
    </section>
@endsection
