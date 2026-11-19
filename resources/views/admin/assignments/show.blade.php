@extends('admin.layout')

@section('title', 'Historique d’affectation')

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <a class="small text-decoration-none" href="{{ route('assignments.index') }}"><i class="bi bi-arrow-left me-1"></i>Retour aux affectations</a>
            <p class="text-primary text-uppercase fw-semibold small mt-3 mb-1">{{ $asset->asset_code }}</p>
            <h1 class="h3 mb-1">{{ $asset->designation }}</h1>
            <p class="text-secondary mb-0">Catégorie : {{ $asset->category->name }}</p>
        </div>
        <a class="btn btn-outline-primary flex-shrink-0" href="{{ route('assets.show', $asset) }}">Fiche du bien</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-5">
            <section class="card content-card mb-3">
                <div class="card-body p-3 p-md-4">
                    <h2 class="h5 mb-3">Détenteur actuel</h2>
                    @if ($activeAssignment)
                        <p class="mb-1 fw-semibold">
                            @if ($activeAssignment->agent)
                                <a href="{{ route('assignments.agent', $activeAssignment->agent) }}">{{ $activeAssignment->agent->last_name }} {{ $activeAssignment->agent->first_name }}</a>
                            @else
                                <a href="{{ route('assignments.service', $activeAssignment->service) }}">{{ $activeAssignment->service->name }}</a>
                            @endif
                        </p>
                        <p class="small text-secondary mb-0">Depuis le {{ $activeAssignment->assigned_at->format('d/m/Y H:i') }}</p>
                    @else
                        <p class="text-secondary mb-0">Ce bien n’a pas d’affectation active.</p>
                    @endif
                </div>
            </section>

            @can('manage-assignments')
                @if ($activeAssignment)
                    <section class="card content-card mb-3">
                        <div class="card-body p-3 p-md-4">
                            <h2 class="h5 mb-3">Transférer</h2>
                            @include('admin.assignments._form', [
                                'action' => route('assignments.transfer', $asset),
                                'asset' => $asset,
                                'includeAsset' => false,
                                'submitLabel' => 'Enregistrer le transfert',
                            ])
                        </div>
                    </section>
                    <section class="card content-card">
                        <div class="card-body p-3 p-md-4">
                            <h2 class="h5 mb-3">Restituer</h2>
                            <form method="POST" action="{{ route('assignments.return', $asset) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label" for="returned_at">Date de restitution</label>
                                    <input class="form-control @error('returned_at') is-invalid @enderror" id="returned_at" name="returned_at" type="datetime-local" value="{{ old('returned_at', now()->format('Y-m-d\TH:i')) }}" required>
                                    @error('returned_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="return_reason">Motif de restitution</label>
                                    <input class="form-control @error('reason') is-invalid @enderror" id="return_reason" name="reason" value="{{ old('reason') }}" maxlength="120" required>
                                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3"><label class="form-label" for="received_by_name">Personne réceptionnaire</label><input class="form-control" id="received_by_name" name="received_by_name" value="{{ old('received_by_name') }}" maxlength="160"></div>
                                <div class="mb-3"><label class="form-label" for="return_condition">État physique constaté</label><select class="form-select" id="return_condition" name="return_condition"><option value="">Non renseigné</option>@foreach (['new' => 'Neuf', 'good' => 'Bon', 'fair' => 'Moyen', 'poor' => 'Mauvais', 'unserviceable' => 'Hors service'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
                                <div class="mb-3"><label class="form-label" for="return_observations">Observations de restitution</label><textarea class="form-control" id="return_observations" name="return_observations" rows="2" maxlength="5000">{{ old('return_observations') }}</textarea></div>
                                <button class="btn btn-outline-primary" type="submit">Enregistrer la restitution</button>
                            </form>
                        </div>
                    </section>
                @else
                    <a class="btn btn-primary w-100" href="{{ route('assignments.create', ['asset_id' => $asset->id]) }}">Affecter ce bien</a>
                @endif
            @endcan
        </div>

        <div class="col-12 col-xl-7">
            <section class="card content-card h-100">
                <div class="card-body p-3 p-md-4">
                    <h2 class="h5 mb-3"><i class="bi bi-clock-history me-1"></i>Historique des mouvements</h2>
                    <h3 class="h6">Affectations et transferts</h3>
                    @forelse ($asset->assignments as $assignment)
                        @php($followedByTransfer = $assignment->ended_at && $asset->assignments->contains(fn ($entry) => $entry->movement_type === 'transfer' && $entry->assigned_at->equalTo($assignment->ended_at)))
                        <article class="border-start border-3 ps-3 pb-4 {{ $loop->last ? 'pb-0' : '' }}">
                            <p class="small text-secondary mb-1">
                                {{ $assignment->assigned_at->format('d/m/Y H:i') }}
                                @if ($assignment->ended_at)
                                    — clôturée le {{ $assignment->ended_at->format('d/m/Y H:i') }}
                                @else
                                    <span class="badge text-bg-success ms-1">Active</span>
                                @endif
                            </p>
                            <p class="mb-1">
                                @if ($assignment->agent)
                                    Agent : {{ $assignment->agent->last_name }} {{ $assignment->agent->first_name }}
                                @else
                                    Service : {{ $assignment->service->name }}
                                @endif
                            </p>
                            <p class="small mb-1">{{ $assignment->reason }}</p>
                            @if ($assignment->observations)
                                <p class="small mb-1">{{ $assignment->observations }}</p>
                            @endif
                            @if ($assignment->end_reason)
                                <p class="small mb-1">Clôture : {{ $assignment->end_reason }}</p>
                            @endif
                            @if ($assignment->received_by_name || $assignment->return_condition || $assignment->return_observations)
                                <p class="small mb-1">Restitué à : {{ $assignment->received_by_name ?: 'Non précisé' }} · État : {{ $assignment->return_condition ?: 'Non renseigné' }}</p>
                                @if ($assignment->return_observations)<p class="small">{{ $assignment->return_observations }}</p>@endif
                            @endif
                            <p class="small text-secondary mb-0">
                                Affecté par {{ $assignment->assigner->name }}
                                @if ($assignment->ender)
                                    · Clôturé par {{ $assignment->ender->name }}
                                @endif
                            </p>
                            <div class="d-flex flex-wrap gap-2 mt-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('documents.assignments.show', $assignment) }}">Fiche d’affectation</a>@if ($assignment->movement_type === 'transfer')<a class="btn btn-sm btn-outline-warning" href="{{ route('documents.transfers.show', $assignment) }}">PV de transfert</a>@endif @if ($assignment->ended_at && $assignment->end_reason && ! $followedByTransfer)<a class="btn btn-sm btn-outline-success" href="{{ route('documents.restitutions.show', $assignment) }}">PV de restitution</a>@endif</div>
                        </article>
                    @empty
                        <p class="text-secondary text-center py-4 mb-0">Aucun mouvement enregistré pour ce bien.</p>
                    @endforelse
                    <h3 class="h6 mt-4">Déplacements physiques</h3>
                    @forelse ($asset->locationHistory->sortByDesc('moved_at') as $movement)
                        <article class="border-start border-3 border-info ps-3 pb-3">
                            <p class="small text-secondary mb-1">{{ $movement->moved_at->format('d/m/Y H:i') }} · {{ $movement->reason }}</p>
                            <p class="mb-1">{{ $movement->fromLocation?->name ?? 'Non renseignée' }} → {{ $movement->toLocation?->name ?? 'Non renseignée' }}</p>
                            @if ($movement->notes)<p class="small mb-1">{{ $movement->notes }}</p>@endif
                            <p class="small text-secondary mb-0">Enregistré par {{ $movement->recorder?->name ?? 'Système / import' }}</p>
                        </article>
                    @empty
                        <p class="small text-secondary">Aucun déplacement physique.</p>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
    @can('manage-assignments')
        <section class="card content-card mt-3"><div class="card-body p-3 p-md-4">
            <h2 class="h5">Enregistrer un déplacement physique</h2>
            <p class="small text-secondary">Ce mouvement modifie uniquement le lieu du bien ; il ne change pas son détenteur administratif.</p>
            <form method="POST" action="{{ route('assignments.move', $asset) }}" class="row g-3">
                @csrf
                <div class="col-12 col-md-5"><label class="form-label" for="movement_location_id">Nouvelle localisation</label><select class="form-select @error('location_id') is-invalid @enderror" id="movement_location_id" name="location_id" required><option value="">Choisir un emplacement</option>@foreach ($locations as $location)<option value="{{ $location->id }}">{{ collect([$location->parent?->parent?->name, $location->parent?->name, $location->name])->filter()->implode(' / ') }}</option>@endforeach</select>@error('location_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12 col-md-3"><label class="form-label" for="moved_at">Date du mouvement</label><input class="form-control @error('moved_at') is-invalid @enderror" id="moved_at" name="moved_at" type="datetime-local" value="{{ now()->format('Y-m-d\\TH:i') }}" required>@error('moved_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12 col-md-4"><label class="form-label" for="movement_reason">Motif</label><input class="form-control @error('reason') is-invalid @enderror" id="movement_reason" name="reason" maxlength="120" required>@error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
                <div class="col-12"><label class="form-label" for="movement_notes">Observations</label><textarea class="form-control" id="movement_notes" name="notes" rows="2" maxlength="5000"></textarea></div>
                <div class="col-12"><button class="btn btn-outline-primary" type="submit">Enregistrer le déplacement</button></div>
            </form>
        </div></section>
    @endcan
@endsection
