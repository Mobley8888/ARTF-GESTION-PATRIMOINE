<?php

namespace App\Http\Controllers;

use App\Enums\InventoryItemStatus;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetLocationHistory;
use App\Models\Category;
use App\Models\Direction;
use App\Models\InventoryCampaign;
use App\Models\InventoryItem;
use App\Models\Location;
use App\Models\Service;
use App\Models\User;
use App\Services\AdministrativeDocumentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class AdministrativeDocumentController extends Controller
{
    public function __construct(private readonly AdministrativeDocumentService $documents) {}

    public function assetSheet(Asset $asset): View
    {
        [$title, $reference, $sections] = $this->assetDocument($asset);

        return $this->documents->preview($title, $reference, $sections, route('documents.assets.pdf', $asset));
    }

    public function assetSheetPdf(Asset $asset): Response
    {
        [$title, $reference, $sections] = $this->assetDocument($asset);

        return $this->documents->pdf($title, $reference, $sections, $asset->asset_code.'-fiche.pdf');
    }

    public function assignmentSheet(AssetAssignment $assignment): View
    {
        [$title, $reference, $sections] = $this->assignmentDocument($assignment);

        return $this->documents->preview($title, $reference, $sections, route('documents.assignments.pdf', $assignment));
    }

    public function assignmentSheetPdf(AssetAssignment $assignment): Response
    {
        [$title, $reference, $sections] = $this->assignmentDocument($assignment);

        return $this->documents->pdf($title, $reference, $sections, $reference.'-affectation.pdf');
    }

    public function transferReport(AssetAssignment $assignment): View
    {
        [$title, $reference, $sections] = $this->transferDocument($assignment);

        return $this->documents->preview($title, $reference, $sections, route('documents.transfers.pdf', $assignment));
    }

    public function transferReportPdf(AssetAssignment $assignment): Response
    {
        [$title, $reference, $sections] = $this->transferDocument($assignment);

        return $this->documents->pdf($title, $reference, $sections, $reference.'-transfert.pdf');
    }

    public function restitutionReport(AssetAssignment $assignment): View
    {
        [$title, $reference, $sections] = $this->restitutionDocument($assignment);

        return $this->documents->preview($title, $reference, $sections, route('documents.restitutions.pdf', $assignment));
    }

    public function restitutionReportPdf(AssetAssignment $assignment): Response
    {
        [$title, $reference, $sections] = $this->restitutionDocument($assignment);

        return $this->documents->pdf($title, $reference, $sections, $reference.'-restitution.pdf');
    }

    public function assetsIndex(Request $request): View
    {
        $filters = $this->assetFilters($request);
        $assets = $this->assetQuery($filters)->with(['category.parent', 'location.parent.parent', 'activeAssignment.agent.service.direction', 'activeAssignment.service.direction'])->orderBy('asset_code')->paginate(30)->withQueryString();

        return view('admin.documents.assets', [
            'assets' => $assets,
            'filters' => $filters,
            'directions' => Direction::query()->orderBy('name')->get(),
            'services' => Service::query()->orderBy('name')->get(),
            'locations' => Location::query()->orderBy('name')->get(),
            'categories' => Category::query()->orderBy('name')->get(),
            'conditions' => $this->conditionLabels(),
            'statuses' => $this->statusLabels(),
        ]);
    }

    public function assetsPdf(Request $request): Response
    {
        $filters = $this->assetFilters($request);
        $assets = $this->assetQuery($filters)->with(['category.parent', 'location.parent.parent', 'activeAssignment.agent.service', 'activeAssignment.service'])->orderBy('asset_code')->get();
        $sections = ['Synthèse' => ['Nombre de biens' => $assets->count(), 'Filtres appliqués' => collect($filters)->filter()->map(fn ($value, $key) => $key.'='.$value)->implode(' ; ') ?: 'Aucun']];
        foreach ($assets as $asset) {
            $sections['État du patrimoine — '.$asset->asset_code] = [
                'Désignation' => $asset->designation,
                'Direction / service' => $this->holderDirection($asset),
                'Catégorie' => $asset->category?->parent?->name.' / '.$asset->category?->name,
                'Localisation' => $this->locationLabel($asset->location),
                'État' => $this->conditionLabels()[$asset->condition?->value ?? ''] ?? 'Non renseigné',
                'Statut' => $this->statusLabels()[$asset->status?->value ?? ''] ?? 'Non renseigné',
            ];
        }

        return $this->documents->pdf('État récapitulatif du patrimoine', 'ETAT-PATRIMOINE-'.now()->format('Ymd-His'), $sections, 'etat-patrimoine.pdf');
    }

    public function assetsExcel(Request $request): Response
    {
        $assets = $this->assetQuery($this->assetFilters($request))->with(['category.parent', 'location.parent.parent', 'activeAssignment.agent.service', 'activeAssignment.service'])->orderBy('asset_code')->get();
        $rows = $assets->map(fn (Asset $asset) => [
            $asset->asset_code,
            $asset->designation,
            $asset->category?->parent?->name.' / '.$asset->category?->name,
            $asset->brand,
            $asset->model,
            $this->holderDirection($asset),
            $this->locationLabel($asset->location),
            $this->conditionLabels()[$asset->condition?->value ?? ''] ?? 'Non renseigné',
            $this->statusLabels()[$asset->status?->value ?? ''] ?? 'Non renseigné',
        ])->all();

        return $this->documents->spreadsheet('État récapitulatif du patrimoine', ['Numéro inventaire', 'Désignation', 'Catégorie', 'Marque', 'Modèle', 'Direction / service détenteur', 'Localisation', 'État physique', 'Statut patrimonial'], $rows, 'etat-patrimoine.xls');
    }

    public function inventoryFinding(InventoryItem $item, Request $request): View
    {
        [$title, $reference, $sections] = $this->inventoryItemDocument($item, $request->user());

        return $this->documents->preview($title, $reference, $sections, route('documents.inventory.findings.pdf', $item));
    }

    public function inventoryFindingPdf(InventoryItem $item, Request $request): Response
    {
        [$title, $reference, $sections] = $this->inventoryItemDocument($item, $request->user());

        return $this->documents->pdf($title, $reference, $sections, $reference.'-constat.pdf');
    }

    public function inventoryClosure(InventoryCampaign $campaign, Request $request): View
    {
        [$title, $reference, $sections] = $this->inventoryClosureDocument($campaign, $request->user());

        return $this->documents->preview($title, $reference, $sections, route('documents.inventory.closure.pdf', $campaign));
    }

    public function inventoryClosurePdf(InventoryCampaign $campaign, Request $request): Response
    {
        [$title, $reference, $sections] = $this->inventoryClosureDocument($campaign, $request->user());

        return $this->documents->pdf($title, $reference, $sections, $reference.'-cloture-inventaire.pdf');
    }

    private function assetDocument(Asset $asset): array
    {
        $asset->load(['category.parent', 'location.parent.parent', 'activeAssignment.agent.service.direction', 'activeAssignment.service.direction', 'assignments.agent.service', 'assignments.service', 'locationHistory.fromLocation.parent.parent', 'locationHistory.toLocation.parent.parent', 'locationHistory.recorder']);
        $assignment = $asset->activeAssignment;
        $holder = $assignment?->agent ? trim($assignment->agent->first_name.' '.$assignment->agent->last_name) : $assignment?->service?->name;
        $assignmentHistory = $asset->assignments->map(fn (AssetAssignment $entry) => $entry->assigned_at->format('d/m/Y').' — '.($entry->agent ? $entry->agent->first_name.' '.$entry->agent->last_name : $entry->service?->name).' — '.($entry->ended_at?->format('d/m/Y') ?? 'En cours').' ('.$entry->movement_type.')')->all();
        $locationHistory = $asset->locationHistory->map(fn (AssetLocationHistory $entry) => $entry->moved_at->format('d/m/Y').' — '.$this->locationLabel($entry->fromLocation).' → '.$this->locationLabel($entry->toLocation).' — '.$entry->reason)->all();

        return ['Fiche individuelle du bien', $asset->asset_code, [
            'Identification' => ['Numéro d’inventaire' => $asset->asset_code, 'Désignation' => $asset->designation, 'Description' => $asset->description, 'Catégorie' => trim(($asset->category?->parent?->name ? $asset->category->parent->name.' / ' : '').($asset->category?->name ?? '')), 'Marque' => $asset->brand, 'Modèle' => $asset->model, 'Numéro de série' => $asset->serial_number, 'Référence constructeur' => $asset->manufacturer_reference],
            'Données patrimoniales' => ['Acquisition' => $asset->acquired_on?->format('d/m/Y'), 'Mise en service' => $asset->commissioned_on?->format('d/m/Y'), 'Mode / fournisseur' => trim(($asset->acquisition_mode ?? '').' / '.($asset->supplier ?? ''), ' /'), 'Facture / livraison' => trim(($asset->invoice_reference ?? '').' / '.($asset->delivery_reference ?? ''), ' /'), 'Valeur d’acquisition' => $asset->acquisition_value === null ? null : number_format((float) $asset->acquisition_value, 2, ',', ' ').' FCFA', 'État physique' => $this->conditionLabels()[$asset->condition?->value ?? ''] ?? null, 'Situation d’utilisation' => $asset->usage_status, 'Statut patrimonial' => $this->statusLabels()[$asset->status?->value ?? ''] ?? null],
            'Détention et localisation' => ['Localisation actuelle' => $this->locationLabel($asset->location), 'Direction' => $assignment?->service?->direction?->name ?? $assignment?->agent?->service?->direction?->name, 'Service détenteur' => $assignment?->service?->name ?? $assignment?->agent?->service?->name, 'Agent responsable' => $holder, 'Affecté depuis' => $assignment?->assigned_at?->format('d/m/Y H:i')],
            'Historique des affectations' => ['Événements' => $assignmentHistory ?: ['Aucun événement enregistré']],
            'Historique des localisations' => ['Événements' => $locationHistory ?: ['Aucun événement enregistré']],
            'Signatures' => ['Gestionnaire' => 'Nom et signature :', 'Responsable / détenteur' => 'Nom et signature :', 'Observations' => ''],
        ]];
    }

    private function assignmentDocument(AssetAssignment $assignment): array
    {
        $assignment->load(['asset.category', 'asset.location.parent.parent', 'agent.service.direction', 'service.direction', 'assigner']);
        $reference = 'AFF-'.$assignment->getKey();

        return ['Fiche d’affectation', $reference, [
            'Bien concerné' => ['Numéro d’inventaire' => $assignment->asset->asset_code, 'Désignation' => $assignment->asset->designation, 'Catégorie' => $assignment->asset->category?->name, 'Localisation' => $this->locationLabel($assignment->asset->location)],
            'Bénéficiaire' => ['Agent' => $assignment->agent ? $assignment->agent->first_name.' '.$assignment->agent->last_name.' ('.$assignment->agent->employee_code.')' : null, 'Service' => $assignment->service?->name ?? $assignment->agent?->service?->name, 'Direction' => $assignment->service?->direction?->name ?? $assignment->agent?->service?->direction?->name],
            'Opération' => ['Date d’affectation' => $assignment->assigned_at->format('d/m/Y H:i'), 'Motif' => $assignment->reason, 'Observations' => $assignment->observations, 'Enregistré par' => $assignment->assigner?->name],
            'Signatures' => ['Remis par' => 'Nom, fonction et signature :', 'Bénéficiaire' => 'Nom et signature :', 'Observations' => ''],
        ]];
    }

    private function transferDocument(AssetAssignment $assignment): array
    {
        abort_unless($assignment->movement_type === 'transfer', 404);
        $assignment->load(['asset.category', 'asset.locationHistory.fromLocation.parent.parent', 'asset.locationHistory.toLocation.parent.parent', 'agent.service.direction', 'service.direction']);
        $previous = $assignment->asset->assignments()->where('assigned_at', '<', $assignment->assigned_at)->orderByDesc('assigned_at')->first();
        $physicalMovement = $assignment->asset->locationHistory->first(fn (AssetLocationHistory $entry) => $entry->moved_at->equalTo($assignment->assigned_at));
        $movementBefore = $assignment->asset->locationHistory->filter(fn (AssetLocationHistory $entry) => $entry->moved_at->lessThan($assignment->assigned_at))->last();
        $movementAfter = $assignment->asset->locationHistory->first(fn (AssetLocationHistory $entry) => $entry->moved_at->greaterThan($assignment->assigned_at));
        $locationAtTransfer = $movementBefore?->toLocation ?? $movementAfter?->fromLocation ?? $assignment->asset->location;
        $oldLocation = $physicalMovement?->fromLocation ?? $locationAtTransfer;
        $newLocation = $physicalMovement?->toLocation ?? $locationAtTransfer;
        $reference = 'TRF-'.$assignment->getKey();

        return ['Procès-verbal de transfert', $reference, [
            'Bien transféré' => ['Numéro d’inventaire' => $assignment->asset->asset_code, 'Désignation' => $assignment->asset->designation, 'Catégorie' => $assignment->asset->category?->name],
            'Situation antérieure' => ['Détenteur précédent' => $previous?->agent ? $previous->agent->first_name.' '.$previous->agent->last_name : $previous?->service?->name, 'Localisation antérieure enregistrée' => $this->locationLabel($oldLocation)],
            'Nouvelle situation' => ['Nouveau détenteur' => $assignment->agent ? $assignment->agent->first_name.' '.$assignment->agent->last_name : $assignment->service?->name, 'Service' => $assignment->service?->name ?? $assignment->agent?->service?->name, 'Localisation enregistrée au mouvement' => $this->locationLabel($newLocation), 'Date' => $assignment->assigned_at->format('d/m/Y H:i'), 'Motif' => $assignment->reason, 'Observations' => $assignment->observations],
            'Précision de gestion' => ['Localisation' => $physicalMovement ? 'Mouvement physique enregistré à la même date.' : 'Le transfert administratif ne modifie pas la localisation physique ; celle-ci est reprise de l’historique à la date du transfert.'],
            'Signatures' => ['Cédant' => 'Nom et signature :', 'Bénéficiaire' => 'Nom et signature :', 'Gestionnaire' => 'Nom et signature :'],
        ]];
    }

    private function restitutionDocument(AssetAssignment $assignment): array
    {
        $followedByTransfer = $assignment->asset->assignments()->where('movement_type', 'transfer')->where('assigned_at', $assignment->ended_at)->exists();
        abort_unless($assignment->ended_at !== null && $assignment->end_reason !== null && ! $followedByTransfer, 404);
        $assignment->load(['asset.category', 'agent.service.direction', 'service.direction', 'ender']);

        return ['Procès-verbal de restitution', 'RET-'.$assignment->getKey(), [
            'Bien restitué' => ['Numéro d’inventaire' => $assignment->asset->asset_code, 'Désignation' => $assignment->asset->designation, 'Catégorie' => $assignment->asset->category?->name],
            'Restitution' => ['Agent / détenteur' => $assignment->agent ? $assignment->agent->first_name.' '.$assignment->agent->last_name : $assignment->service?->name, 'Date' => $assignment->ended_at->format('d/m/Y H:i'), 'Motif' => $assignment->end_reason, 'État physique constaté' => $this->conditionLabels()[$assignment->return_condition ?? ''] ?? null, 'Réceptionnaire' => $assignment->received_by_name, 'Observations' => $assignment->return_observations, 'Enregistré par' => $assignment->ender?->name],
            'Signatures' => ['Restituant' => 'Nom et signature :', 'Réceptionnaire' => 'Nom et signature :', 'Gestionnaire' => 'Nom et signature :'],
        ]];
    }

    private function inventoryItemDocument(InventoryItem $item, User $user): array
    {
        $item->load(['campaign', 'task.agent.service.direction', 'asset', 'observedLocation.parent.parent', 'observedAgent.service.direction', 'observedService.direction', 'observer', 'evidence']);
        $agentId = $this->restrictedAgentId($user);
        abort_unless(! $agentId || (int) $item->task?->agent_id === $agentId, 404);
        $expected = $item->expected_snapshot ?? [];

        return ['Fiche de constatation d’inventaire', 'INV-'.$item->campaign_id.'-'.$item->getKey(), [
            'Campagne et mission' => ['Référence' => $item->campaign->reference, 'Campagne' => $item->campaign->title, 'Périmètre' => $item->task?->scope?->label(), 'Agent recenseur' => $item->task?->agent?->first_name.' '.$item->task?->agent?->last_name],
            'Données théoriques' => ['Numéro d’inventaire' => $expected['asset_code'] ?? $item->asset?->asset_code, 'Désignation' => $expected['designation'] ?? $item->asset?->designation, 'Numéro de série' => $expected['serial_number'] ?? $item->asset?->serial_number, 'Localisation' => $expected['location'] ?? null, 'Détenteur' => $expected['agent'] ?? $expected['service'] ?? null],
            'Constat physique' => ['Statut' => $this->itemStatusLabels()[$item->status->value], 'Référence constatée' => $item->observed_asset_code, 'Désignation constatée' => $item->observed_designation, 'Localisation constatée' => $this->locationLabel($item->observedLocation), 'Agent constaté' => $item->observedAgent?->first_name.' '.$item->observedAgent?->last_name, 'Service constaté' => $item->observedService?->name, 'État physique' => $this->conditionLabels()[$item->physical_condition ?? ''] ?? null, 'Date du constat' => $item->observed_at?->format('d/m/Y H:i'), 'Saisi par' => $item->observer?->name],
            'Écarts et justificatifs' => ['Anomalie physique' => $item->physical_anomaly, 'Anomalie administrative' => $item->administrative_anomaly, 'Observations' => $item->observations, 'Pièces jointes' => $item->evidence->pluck('original_name')->all() ?: ['Aucune']],
            'Validation du constat' => ['Agent recenseur' => 'Nom et signature :', 'Vérificateur' => 'Nom et signature :', 'Observations' => ''],
        ]];
    }

    private function inventoryClosureDocument(InventoryCampaign $campaign, User $user): array
    {
        abort_unless($campaign->status->value === 'closed', 404);
        abort_unless(! $this->restrictedAgentId($user), 403);
        $campaign->load(['creator', 'responsible', 'validator', 'closer', 'reviews.reviewer']);
        $counts = $campaign->items()->selectRaw('status, COUNT(*) AS aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $closure = $campaign->reviews->where('step', 'closure')->last();

        return ['Procès-verbal de clôture d’inventaire', $campaign->reference.'-CLOTURE', [
            'Campagne' => ['Référence' => $campaign->reference, 'Intitulé' => $campaign->title, 'Période' => $campaign->starts_on->format('d/m/Y').' au '.$campaign->ends_on->format('d/m/Y'), 'Responsable' => $campaign->responsible->name, 'Lancement' => $campaign->launched_at?->format('d/m/Y H:i')],
            'Bilan' => ['Biens attendus' => $campaign->items()->whereNotNull('asset_id')->count(), 'Constats saisis' => $campaign->items()->where('status', '!=', InventoryItemStatus::Pending->value)->count(), 'Biens restants' => $counts[InventoryItemStatus::Pending->value] ?? 0, 'Écarts' => $campaign->items()->whereIn('status', ['present_anomaly', 'absent', 'moved', 'damaged', 'unidentified', 'discovered'])->count()],
            'Validation et clôture' => ['Validé par' => $campaign->validator?->name, 'Date de validation' => $campaign->validated_at?->format('d/m/Y H:i'), 'Clôturé par' => $campaign->closer?->name, 'Date de clôture' => $campaign->closed_at?->format('d/m/Y H:i'), 'Observations de clôture' => $closure?->observations],
            'Signatures' => ['Responsable de campagne' => 'Nom et signature :', 'Vérificateur' => 'Nom et signature :', 'Autorité de validation' => 'Nom et signature :'],
        ]];
    }

    private function assetFilters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'direction_id' => ['nullable', 'integer', 'exists:directions,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'condition' => ['nullable', 'in:new,good,fair,poor,unserviceable'],
            'status' => ['nullable', 'in:in_service,under_maintenance,retired,disposed,lost'],
        ]);
    }

    private function assetQuery(array $filters): Builder
    {
        return Asset::query()
            ->when($filters['q'] ?? null, fn (Builder $query, string $term) => $query->where(fn (Builder $q) => $q->where('asset_code', 'like', "%{$term}%")->orWhere('designation', 'like', "%{$term}%")->orWhere('serial_number', 'like', "%{$term}%")))
            ->when($filters['direction_id'] ?? null, fn (Builder $query, string $id) => $query->whereHas('activeAssignment', fn (Builder $assignment) => $assignment->whereHas('service', fn (Builder $service) => $service->where('direction_id', $id))->orWhereHas('agent.service', fn (Builder $service) => $service->where('direction_id', $id))))
            ->when($filters['service_id'] ?? null, fn (Builder $query, string $id) => $query->whereHas('activeAssignment', fn (Builder $assignment) => $assignment->where('service_id', $id)->orWhereHas('agent', fn (Builder $agent) => $agent->where('service_id', $id))))
            ->when($filters['location_id'] ?? null, fn (Builder $query, string $id) => $query->where('location_id', $id))
            ->when($filters['category_id'] ?? null, fn (Builder $query, string $id) => $query->where('category_id', $id))
            ->when($filters['condition'] ?? null, fn (Builder $query, string $value) => $query->where('condition', $value))
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value));
    }

    private function locationLabel(?Location $location): ?string
    {
        if (! $location) {
            return null;
        }

        $parts = [];
        while ($location) {
            $parts[] = $location->name;
            $location = $location->parent;
        }

        return implode(' / ', array_reverse($parts));
    }

    private function holderDirection(Asset $asset): ?string
    {
        $assignment = $asset->activeAssignment;

        return $assignment?->service?->direction?->name ?? $assignment?->agent?->service?->direction?->name;
    }

    private function conditionLabels(): array
    {
        return ['new' => 'Neuf', 'good' => 'Bon', 'fair' => 'Moyen', 'poor' => 'Mauvais', 'unserviceable' => 'Hors service'];
    }

    private function statusLabels(): array
    {
        return ['in_service' => 'En service', 'under_maintenance' => 'En maintenance', 'retired' => 'Réformé', 'disposed' => 'Cédé', 'lost' => 'Perdu'];
    }

    private function itemStatusLabels(): array
    {
        return ['pending' => 'À contrôler', 'present_conform' => 'Présent et conforme', 'present_anomaly' => 'Présent avec anomalie', 'absent' => 'Absent', 'moved' => 'Déplacé', 'damaged' => 'Détérioré', 'unidentified' => 'Non identifié', 'discovered' => 'Découvert non enregistré'];
    }

    private function restrictedAgentId(User $user): ?int
    {
        if ($user->can('manage-inventories') || $user->can('verify-inventories') || $user->can('validate-inventories') || $user->can('close-inventories')) {
            return null;
        }

        return $user->agent ? (int) $user->agent->getKey() : null;
    }
}
