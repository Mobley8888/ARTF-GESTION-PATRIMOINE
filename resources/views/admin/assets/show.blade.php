@extends('admin.layout')

@section('title', $asset->asset_code)

@section('content')
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
        <div>
            <a class="small text-decoration-none" href="{{ route('assets.index') }}"><i class="bi bi-arrow-left me-1"></i>Retour aux biens</a>
            <p class="text-primary text-uppercase fw-semibold small mt-3 mb-1">{{ $asset->asset_code }}</p>
            <h1 class="h3 mb-1">{{ $asset->designation }}</h1>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-success flex-shrink-0" href="{{ route('documents.assets.show', $asset) }}">Fiche imprimable</a>
            @can('view-assignments')
                <a class="btn btn-outline-primary flex-shrink-0" href="{{ route('assignments.show', $asset) }}">
                    <i class="bi bi-clock-history me-1"></i> Historique des affectations
                </a>
            @endcan
            @can('manage-assets')
                <a class="btn btn-primary flex-shrink-0" href="{{ route('assets.edit', $asset) }}">
                    <i class="bi bi-pencil me-1"></i> Modifier
                </a>
                <form method="POST" action="{{ route('assets.destroy', $asset) }}" onsubmit="return confirm('Archiver ce bien ? Ses historiques seront conservés.')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger" type="submit">
                        <i class="bi bi-archive me-1"></i> Archiver
                    </button>
                </form>
            @endcan
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($errors->has('asset'))
        <div class="alert alert-danger" role="alert">{{ $errors->first('asset') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <section class="card content-card h-100">
                <div class="card-body p-3 p-md-4">
                    <h2 class="h5 mb-3">Informations du bien</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-5 text-secondary fw-normal">Code</dt>
                        <dd class="col-sm-7 fw-semibold">{{ $asset->asset_code }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Désignation</dt>
                        <dd class="col-sm-7">{{ $asset->designation }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Catégorie</dt>
                        <dd class="col-sm-7">{{ $asset->category->parent ? $asset->category->parent->name.' / ' : '' }}{{ $asset->category->name }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Localisation</dt>
                        <dd class="col-sm-7">
                            @if ($asset->location)
                                {{ collect([$asset->location->parent?->parent?->name, $asset->location->parent?->name, $asset->location->name])->filter()->implode(' / ') }}
                            @else
                                Non renseignée
                            @endif
                        </dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Numéro de série</dt>
                        <dd class="col-sm-7">{{ $asset->serial_number ?: 'Non renseigné' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Fabricant / marque / modèle</dt>
                        <dd class="col-sm-7">{{ collect([$asset->manufacturer, $asset->brand, $asset->model])->filter()->implode(' / ') ?: 'Non renseigné' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Référence constructeur</dt>
                        <dd class="col-sm-7">{{ $asset->manufacturer_reference ?: 'Non renseignée' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Date d’acquisition</dt>
                        <dd class="col-sm-7">{{ $asset->acquired_on?->format('d/m/Y') ?? 'Non renseignée' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Mise en service</dt>
                        <dd class="col-sm-7">{{ $asset->commissioned_on?->format('d/m/Y') ?? 'Non renseignée' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Mode / fournisseur</dt>
                        <dd class="col-sm-7">{{ collect([$asset->acquisition_mode, $asset->supplier])->filter()->implode(' / ') ?: 'Non renseigné' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Facture / livraison</dt>
                        <dd class="col-sm-7">{{ collect([$asset->invoice_reference, $asset->delivery_reference])->filter()->implode(' / ') ?: 'Non renseignées' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Valeur d’acquisition</dt>
                        <dd class="col-sm-7">{{ $asset->acquisition_value !== null ? number_format((float) $asset->acquisition_value, 2, ',', ' ').' FCFA' : 'Non renseignée' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">État physique</dt>
                        <dd class="col-sm-7">{{ $asset->condition ? $conditionLabels[$asset->condition->value] : 'Non renseigné' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Statut</dt>
                        <dd class="col-sm-7"><span class="badge text-bg-light border">{{ $statusLabels[$asset->status->value] }}</span></dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Situation d’utilisation</dt>
                        <dd class="col-sm-7">{{ ['in_service' => 'En service', 'broken' => 'En panne', 'under_repair' => 'En réparation', 'retired' => 'Réformé', 'other' => 'Autre'][$asset->usage_status] ?? 'Non renseignée' }}</dd>
                        <dt class="col-sm-5 text-secondary fw-normal">Description</dt>
                        <dd class="col-sm-7">{{ $asset->description ?: 'Aucune description' }}</dd>
                    </dl>

                    @if ($asset->characteristics)
                        <hr>
                        <h3 class="h6">Caractéristiques</h3>
                        <pre class="bg-light rounded p-3 mb-0 small text-wrap">{{ json_encode($asset->characteristics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    @endif

                    <hr>
                    <p class="small text-secondary mb-1">Créé par {{ $asset->creator?->name ?? 'Système / import' }}</p>
                    <p class="small text-secondary mb-0">Dernière modification par {{ $asset->updater?->name ?? 'Système / import' }}</p>
                </div>
            </section>
        </div>

        <div class="col-12 col-xl-5">
            <section class="card content-card h-100">
                <div class="card-body p-3 p-md-4">
                    <h2 class="h5 mb-3"><i class="bi bi-clock-history me-1"></i>Historique de localisation</h2>
                    @forelse ($asset->locationHistory as $movement)
                        <article class="border-start border-3 ps-3 pb-4 {{ $loop->last ? 'pb-0' : '' }}">
                            <p class="small text-secondary mb-1">{{ $movement->moved_at->format('d/m/Y H:i') }}</p>
                            <p class="mb-1">
                                {{ $movement->fromLocation?->name ?? 'Non renseignée' }}
                                <i class="bi bi-arrow-right mx-1"></i>
                                {{ $movement->toLocation?->name ?? 'Non renseignée' }}
                            </p>
                            <p class="small mb-1">{{ $movement->reason }}</p>
                            <p class="small text-secondary mb-0">Enregistré par {{ $movement->recorder?->name ?? 'Système / import' }}</p>
                        </article>
                    @empty
                        <div class="text-center text-secondary py-4">
                            <i class="bi bi-geo-alt fs-3 d-block mb-2"></i>
                            Aucun changement de localisation enregistré.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-12 col-xl-6">
            <section class="card content-card h-100"><div class="card-body p-3 p-md-4">
                <h2 class="h5">Affectation actuelle</h2>
                @if ($asset->activeAssignment)
                    <p class="mb-1">{{ $asset->activeAssignment->agent ? $asset->activeAssignment->agent->last_name.' '.$asset->activeAssignment->agent->first_name : 'Service' }}</p>
                    <p class="text-secondary mb-1">{{ $asset->activeAssignment->service?->name ?? $asset->activeAssignment->agent?->service?->name ?? 'Service non précisé' }}</p>
                    <p class="small text-secondary mb-0">Depuis le {{ $asset->activeAssignment->assigned_at->format('d/m/Y') }}</p>
                @else
                    <p class="text-secondary mb-0">Aucune affectation active.</p>
                @endif
                <h3 class="h6 mt-4">Historique des affectations</h3>
                @forelse ($asset->assignments as $assignment)
                    <div class="border-top py-2">
                        <div>{{ $assignment->agent ? $assignment->agent->last_name.' '.$assignment->agent->first_name : ($assignment->service?->name ?? 'Détenteur inconnu') }}</div>
                        <div class="small text-secondary">{{ $assignment->assigned_at->format('d/m/Y') }}{{ $assignment->ended_at ? ' — '.$assignment->ended_at->format('d/m/Y') : ' — En cours' }} · {{ $assignment->reason }}</div>
                    </div>
                @empty
                    <p class="small text-secondary">Aucun historique d’affectation.</p>
                @endforelse
            </div></section>
        </div>
        <div class="col-12 col-xl-6">
            <section class="card content-card h-100"><div class="card-body p-3 p-md-4">
                <h2 class="h5">Documents et photographies</h2>
                @can('manage-assets')
                    <form method="POST" enctype="multipart/form-data" action="{{ route('assets.documents.store', $asset) }}" class="row g-2 mb-3">
                        @csrf
                        <div class="col-sm-4"><label class="form-label" for="document_type">Type</label><select class="form-select" name="type" id="document_type" required>
                            @foreach (['photo' => 'Photographie', 'invoice' => 'Facture', 'delivery_note' => 'Bon de livraison', 'reception_report' => 'PV de réception', 'warranty' => 'Garantie', 'technical_sheet' => 'Fiche technique', 'other' => 'Autre'] as $type => $label)<option value="{{ $type }}">{{ $label }}</option>@endforeach
                        </select></div>
                        <div class="col-sm-8"><label class="form-label" for="documents">Fichiers (10 max., 10 Mo chacun)</label><input class="form-control" id="documents" name="documents[]" type="file" multiple accept=".jpg,.jpeg,.png,.webp,.pdf" required></div>
                        <div class="col-12"><button class="btn btn-sm btn-primary" type="submit">Ajouter les documents</button></div>
                        @error('documents')<div class="text-danger small">{{ $message }}</div>@enderror
                        @error('documents.*')<div class="text-danger small">{{ $message }}</div>@enderror
                    </form>
                @endcan
                @forelse ($asset->documents as $document)
                    <div class="d-flex justify-content-between align-items-center gap-2 border-top py-2">
                        <div><div>{{ $document->original_name }}</div><div class="small text-secondary">{{ ucfirst(str_replace('_', ' ', $document->type)) }} · ajouté le {{ $document->created_at->format('d/m/Y') }} · {{ $document->uploader?->name ?? 'Utilisateur inconnu' }}</div></div>
                        <div class="d-flex gap-2"><a class="btn btn-sm btn-outline-primary" href="{{ route('assets.documents.download', [$asset, $document]) }}">Télécharger</a>
                            @can('manage-assets')<form method="POST" action="{{ route('assets.documents.destroy', [$asset, $document]) }}" onsubmit="return confirm('Supprimer ce document ?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Supprimer</button></form>@endcan
                        </div>
                    </div>
                @empty
                    <p class="text-secondary mb-0">Aucun document associé.</p>
                @endforelse
            </div></section>
        </div>
    </div>

    <section class="card content-card mt-3"><div class="card-body p-3 p-md-4">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div><h2 class="h5 mb-1">Maintenance du bien</h2><p class="small text-secondary mb-0">Le statut de maintenance est suivi séparément de la situation patrimoniale.</p></div>
            <div class="d-flex gap-2">
                @if ($openMaintenance)<span class="badge text-bg-warning align-self-center">{{ $openMaintenance->type === 'corrective' ? 'Panne / demande ouverte' : 'Entretien en cours' }} · {{ $openMaintenance->status->label() }}</span>@endif
                @if (auth()->user()->can('manage-maintenance') || $maintenanceRequests->isNotEmpty())<a class="btn btn-sm btn-outline-secondary" href="{{ route('maintenance.assets.history.pdf', $asset) }}">Historique PDF</a>@endif
                @can('report-maintenance')<a class="btn btn-sm btn-primary" href="{{ route('maintenance.requests.create', ['asset_id' => $asset->id]) }}">Signaler / programmer</a>@endcan
            </div>
        </div>
        @forelse ($maintenanceRequests as $maintenance)
            <div class="border-top py-2 d-flex flex-wrap justify-content-between gap-2">
                <div><a href="{{ route('maintenance.requests.show', $maintenance) }}" class="fw-semibold">{{ $maintenance->reference }} · {{ $maintenance->title }}</a><div class="small text-secondary">{{ $maintenance->type === 'corrective' ? 'Corrective' : 'Préventive' }} · {{ $maintenance->reported_at->format('d/m/Y') }} · {{ $maintenance->assignedAgent ? $maintenance->assignedAgent->last_name.' '.$maintenance->assignedAgent->first_name : 'Non affectée' }}</div></div>
                <span class="badge text-bg-light align-self-center">{{ $maintenance->status->label() }}</span>
            </div>
        @empty
            <p class="text-secondary mb-0">Aucune demande de maintenance accessible pour ce bien.</p>
        @endforelse
        @can('manage-maintenance-schedules')
            @foreach ($asset->maintenanceSchedules as $schedule)
                <div class="border-top py-2 small">Entretien {{ $schedule->title }} · {{ $schedule->frequency }} · prochaine échéance {{ $schedule->next_due_on->format('d/m/Y') }}</div>
            @endforeach
        @endcan
    </div></section>
@endsection
