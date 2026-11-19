<?php

namespace App\Services;

use App\Enums\InventoryCampaignStatus;
use App\Enums\InventoryItemStatus;
use App\Models\Asset;
use App\Models\InventoryCampaign;
use App\Models\InventoryCampaignScope;
use App\Models\InventoryItem;
use App\Models\InventoryReview;
use App\Models\InventoryTask;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryCampaignService
{
    public function previewCount(array $scopeData): int
    {
        $assetIds = collect();

        foreach ($this->scopeModels($scopeData) as $scope) {
            $assetIds = $assetIds->merge($this->assetsForScope($scope)->pluck('assets.id'));
        }

        return $assetIds->unique()->count();
    }

    public function create(array $data, User $actor): InventoryCampaign
    {
        return DB::transaction(function () use ($data, $actor): InventoryCampaign {
            $campaign = InventoryCampaign::query()->create([
                'reference' => $data['reference'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
                'status' => InventoryCampaignStatus::Draft,
                'responsible_user_id' => $actor->getKey(),
                'created_by' => $actor->getKey(),
            ]);

            foreach ($this->scopeModels($data) as $scope) {
                $campaign->scopes()->create($scope);
            }

            $this->recordReview($campaign, $actor, 'campaign_creation', 'created', 'Brouillon et périmètre initial créés.');

            return $campaign->load('scopes');
        });
    }

    public function updateDraft(InventoryCampaign $campaign, array $data, User $actor): InventoryCampaign
    {
        return DB::transaction(function () use ($campaign, $data, $actor): InventoryCampaign {
            $campaign = InventoryCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());
            if ($campaign->status !== InventoryCampaignStatus::Draft) {
                throw ValidationException::withMessages(['campaign' => 'Seuls les brouillons peuvent être modifiés.']);
            }

            $hasTasks = $campaign->tasks()->exists();
            $campaign->fill([
                'reference' => $data['reference'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'],
            ])->save();

            if (! $hasTasks) {
                $campaign->scopes()->delete();
                foreach ($this->scopeModels($data) as $scope) {
                    $campaign->scopes()->create($scope);
                }
            }

            $this->recordReview($campaign, $actor, 'campaign_update', 'updated', 'Informations du brouillon mises à jour.');

            return $campaign->fresh('scopes');
        });
    }

    public function assignTask(InventoryCampaign $campaign, InventoryCampaignScope $scope, int $agentId, User $actor, ?string $dueOn): InventoryTask
    {
        $task = DB::transaction(function () use ($campaign, $scope, $agentId, $actor, $dueOn): InventoryTask {
            $campaign = InventoryCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());
            if ($campaign->status !== InventoryCampaignStatus::Draft) {
                throw ValidationException::withMessages(['campaign' => 'Les opérations ne peuvent être affectées que sur une campagne brouillon.']);
            }

            if ((int) $scope->campaign_id !== (int) $campaign->getKey()) {
                throw ValidationException::withMessages(['scope_id' => 'Le périmètre choisi n’appartient pas à cette campagne.']);
            }

            $task = InventoryTask::query()->firstOrNew(['scope_id' => $scope->getKey()]);
            $previousAgent = $task->exists ? $task->agent()->first() : null;
            $previousDueOn = $task->due_on?->format('Y-m-d');
            $task->fill([
                'campaign_id' => $campaign->getKey(),
                'agent_id' => $agentId,
                'assigned_by' => $actor->getKey(),
                'status' => 'assigned',
                'due_on' => $dueOn,
            ])->save();
            $task->load('agent.service.direction');
            $agentLabel = trim($task->agent->first_name.' '.$task->agent->last_name);
            $serviceLabel = $task->agent->service?->name;
            $directionLabel = $task->agent->service?->direction?->name;
            $decision = $previousAgent ? 'reassigned' : 'assigned';
            $history = ($previousAgent ? 'Réaffectation depuis '.trim($previousAgent->first_name.' '.$previousAgent->last_name).'. ' : '')
                .'Périmètre '.$scope->label().' attribué à '.$agentLabel
                .($serviceLabel ? ' — '.$serviceLabel : '')
                .($directionLabel ? ' / '.$directionLabel : '')
                .'; échéance : '.($dueOn ?? 'non définie')
                .($previousDueOn && $previousDueOn !== $dueOn ? ' (précédente : '.$previousDueOn.')' : '').'.';
            $this->recordReview($campaign, $actor, 'task_assignment', $decision, $history);

            return $task;
        });

        return $task->load(['scope', 'agent']);
    }

    public function launch(InventoryCampaign $campaign, User $actor): InventoryCampaign
    {
        return DB::transaction(function () use ($campaign, $actor): InventoryCampaign {
            $lockedCampaign = InventoryCampaign::query()->lockForUpdate()->with(['scopes.task.agent.service.direction'])->findOrFail($campaign->getKey());

            if ($lockedCampaign->status !== InventoryCampaignStatus::Draft || $lockedCampaign->scopes->isEmpty()) {
                throw ValidationException::withMessages(['campaign' => 'Seule une campagne brouillon avec un périmètre peut être lancée.']);
            }

            if (today()->lt($lockedCampaign->starts_on) || today()->gt($lockedCampaign->ends_on)) {
                throw ValidationException::withMessages(['campaign' => 'La campagne ne peut être lancée qu’entre ses dates planifiées.']);
            }

            foreach ($lockedCampaign->scopes as $scope) {
                if (! $scope->task || ! $scope->task->agent?->is_active) {
                    throw ValidationException::withMessages(['tasks' => 'Attribuez chaque périmètre à un agent actif avant le lancement.']);
                }
            }

            foreach ($lockedCampaign->scopes as $scope) {
                foreach ($this->assetsForScope($scope)->with(['location.parent.parent', 'activeAssignment.agent.service.direction', 'activeAssignment.service.direction'])->orderBy('asset_code')->get() as $asset) {
                    $assignment = $asset->activeAssignment;
                    $asset->inventoryItems()->firstOrCreate(
                        ['campaign_id' => $lockedCampaign->getKey(), 'asset_id' => $asset->getKey()],
                        [
                            'task_id' => $scope->task->getKey(),
                            'expected_snapshot' => [
                                'asset_code' => $asset->asset_code,
                                'designation' => $asset->designation,
                                'serial_number' => $asset->serial_number,
                                'location_id' => $asset->location_id,
                                'location' => $asset->location?->name,
                                'agent_id' => $assignment?->agent_id,
                                'agent' => $assignment?->agent ? trim($assignment->agent->first_name.' '.$assignment->agent->last_name) : null,
                                'service_id' => $assignment?->service_id ?? $assignment?->agent?->service_id,
                                'service' => $assignment?->service?->name ?? $assignment?->agent?->service?->name,
                                'direction_id' => $assignment?->service?->direction_id ?? $assignment?->agent?->service?->direction_id,
                                'direction' => $assignment?->service?->direction?->name ?? $assignment?->agent?->service?->direction?->name,
                                'condition' => $asset->condition?->value,
                            ],
                        ],
                    );
                }

                $scope->task->forceFill(['status' => 'in_progress'])->save();
            }

            $lockedCampaign->forceFill([
                'status' => InventoryCampaignStatus::InProgress,
                'launched_at' => now(),
            ])->save();

            $this->recordReview($lockedCampaign, $actor, 'launch', 'launched', 'Campagne lancée et données théoriques figées.');

            return $lockedCampaign->fresh(['scopes.task', 'items']);
        }, attempts: 3);
    }

    public function capture(InventoryItem $item, array $data, User $actor): InventoryItem
    {
        return DB::transaction(function () use ($item, $data, $actor): InventoryItem {
            $item = InventoryItem::query()->lockForUpdate()->with(['campaign', 'task.agent', 'asset'])->findOrFail($item->getKey());
            $this->assertCampaignInProgress($item->campaign);

            if (! $this->canPerformTask($item->task, $actor)) {
                abort(403);
            }

            $item->fill([...$data, 'observed_by' => $actor->getKey(), 'observed_at' => now()])->save();
            if ($item->task && $item->task->status === 'assigned') {
                $item->task->forceFill(['status' => 'in_progress'])->save();
            }
            $this->recordReview($item->campaign, $actor, 'item_capture', 'updated', 'Constat mis à jour : '.($item->asset?->asset_code ?? $item->observed_asset_code ?? 'bien').' — '.$item->status->value);

            return $item->fresh(['asset', 'observedLocation', 'observedAgent', 'observedService']);
        });
    }

    public function discover(InventoryTask $task, array $data, User $actor): InventoryItem
    {
        $task->loadMissing('campaign', 'agent');

        return DB::transaction(function () use ($task, $data, $actor): InventoryItem {
            $campaign = InventoryCampaign::query()->lockForUpdate()->findOrFail($task->campaign_id);
            $task->refresh()->load('agent');
            $this->assertCampaignInProgress($campaign);
            if (! $this->canPerformTask($task, $actor)) {
                abort(403);
            }
            $code = trim((string) ($data['observed_asset_code'] ?? ''));
            if ($code !== '' && $campaign->items()->where(function (Builder $query) use ($code): void {
                $query->where('observed_asset_code', $code)
                    ->orWhereHas('asset', fn (Builder $asset) => $asset->where('asset_code', $code));
            })->exists()) {
                throw ValidationException::withMessages(['observed_asset_code' => 'Cette référence est déjà recensée dans la campagne.']);
            }

            $data['observed_asset_code'] = $code !== '' ? $code : null;
            $data['campaign_id'] = $task->campaign_id;
            $data['task_id'] = $task->getKey();
            $data['asset_id'] = null;
            $data['status'] = $data['status'] ?? InventoryItemStatus::Discovered->value;
            $data['observed_by'] = $actor->getKey();
            $data['observed_at'] = now();
            $item = InventoryItem::query()->create($data);
            $this->recordReview($campaign, $actor, 'asset_discovery', 'recorded', 'Bien découvert : '.($code !== '' ? $code : ($data['observed_designation'] ?? 'non identifié')));

            return $item;
        });
    }

    public function submitTask(InventoryTask $task, User $actor): void
    {
        DB::transaction(function () use ($task, $actor): void {
            $campaign = InventoryCampaign::query()->lockForUpdate()->findOrFail($task->campaign_id);
            $task = InventoryTask::query()->lockForUpdate()->findOrFail($task->getKey());
            $this->assertCampaignInProgress($campaign);

            if ($task->status === 'submitted') {
                throw ValidationException::withMessages(['task' => 'Cette opération a déjà été soumise.']);
            }

            if (! $this->canPerformTask($task, $actor)) {
                abort(403);
            }

            if ($task->items()->where('status', InventoryItemStatus::Pending->value)->exists()) {
                throw ValidationException::withMessages(['task' => 'Complétez ou signalez tous les biens du périmètre avant de soumettre l’opération.']);
            }

            $task->forceFill(['status' => 'submitted', 'submitted_at' => now()])->save();
            $this->recordReview($campaign, $actor, 'task_submission', 'submitted', 'Opération '.$task->getKey().' soumise.');

            if (! $campaign->tasks()->where('status', '!=', 'submitted')->exists()) {
                $campaign->forceFill(['status' => InventoryCampaignStatus::ToVerify])->save();
                $this->recordReview($campaign, $actor, 'submission', 'submitted', 'Toutes les opérations ont été soumises à vérification.');
            }
        });
    }

    public function verify(InventoryCampaign $campaign, User $actor, bool $approved, ?string $observations): void
    {
        DB::transaction(function () use ($campaign, $actor, $approved, $observations): void {
            $campaign = InventoryCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());
            if ($campaign->status !== InventoryCampaignStatus::ToVerify) {
                throw ValidationException::withMessages(['campaign' => 'La campagne doit être soumise à vérification.']);
            }

            if ($this->pendingDecisionFor($campaign, 'verification') === 'approved') {
                throw ValidationException::withMessages(['campaign' => 'Cette campagne a déjà reçu une vérification favorable.']);
            }

            if ($approved) {
                $this->recordReview($campaign, $actor, 'verification', 'approved', $observations);

                return;
            }

            $campaign->tasks()->update(['status' => 'in_progress', 'submitted_at' => null]);
            $campaign->forceFill(['status' => InventoryCampaignStatus::InProgress])->save();
            $this->recordReview($campaign, $actor, 'verification', 'returned', $observations);
        });
    }

    public function validateCampaign(InventoryCampaign $campaign, User $actor, ?string $observations): void
    {
        DB::transaction(function () use ($campaign, $actor, $observations): void {
            $campaign = InventoryCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());
            $lastVerification = $campaign->reviews()->where('step', 'verification')->latest('reviewed_at')->first();
            if ($campaign->status !== InventoryCampaignStatus::ToVerify || $lastVerification?->decision !== 'approved') {
                throw ValidationException::withMessages(['campaign' => 'La campagne doit avoir reçu une vérification favorable avant validation.']);
            }

            $campaign->forceFill([
                'status' => InventoryCampaignStatus::Validated,
                'validated_by' => $actor->getKey(),
                'validated_at' => now(),
            ])->save();
            $this->recordReview($campaign, $actor, 'validation', 'approved', $observations);
        });
    }

    public function close(InventoryCampaign $campaign, User $actor, ?string $observations): void
    {
        if ($campaign->status !== InventoryCampaignStatus::Validated) {
            throw ValidationException::withMessages(['campaign' => 'Seule une campagne validée peut être clôturée.']);
        }

        DB::transaction(function () use ($campaign, $actor, $observations): void {
            $campaign = InventoryCampaign::query()->lockForUpdate()->findOrFail($campaign->getKey());
            if ($campaign->status !== InventoryCampaignStatus::Validated) {
                throw ValidationException::withMessages(['campaign' => 'Seule une campagne validée peut être clôturée.']);
            }
            $campaign->forceFill([
                'status' => InventoryCampaignStatus::Closed,
                'closed_by' => $actor->getKey(),
                'closed_at' => now(),
            ])->save();
            $this->recordReview($campaign, $actor, 'closure', 'closed', $observations);
        });
    }

    public function canPerformTask(?InventoryTask $task, User $actor): bool
    {
        return $task !== null
            && $task->status !== 'submitted'
            && ($actor->can('manage-inventories') || ($actor->agent?->is_active && $actor->agent->getKey() === $task->agent_id));
    }

    private function assertCampaignInProgress(InventoryCampaign $campaign): void
    {
        if ($campaign->status !== InventoryCampaignStatus::InProgress) {
            throw ValidationException::withMessages(['campaign' => 'La saisie n’est possible que pendant une campagne en cours.']);
        }
    }

    private function recordReview(InventoryCampaign $campaign, User $actor, string $step, string $decision, ?string $observations): InventoryReview
    {
        return $campaign->reviews()->create([
            'reviewed_by' => $actor->getKey(),
            'step' => $step,
            'decision' => $decision,
            'observations' => $observations,
            'reviewed_at' => now(),
        ]);
    }

    private function pendingDecisionFor(InventoryCampaign $campaign, string $step): ?string
    {
        return $campaign->reviews()->where('step', $step)->latest('reviewed_at')->value('decision');
    }

    private function scopeModels(array $data): array
    {
        if (($data['global_scope'] ?? false) || ($data['scope_type'] ?? null) === 'global') {
            return [['scope_type' => 'global']];
        }

        $scopes = [];

        foreach ($data['direction_ids'] ?? [] as $id) {
            $scopes[] = ['scope_type' => 'direction', 'direction_id' => $id];
        }

        foreach ($data['service_ids'] ?? [] as $id) {
            $scopes[] = ['scope_type' => 'service', 'service_id' => $id];
        }

        foreach ($data['location_ids'] ?? [] as $id) {
            $scopes[] = ['scope_type' => 'location', 'location_id' => $id];
        }

        return $scopes;
    }

    private function assetsForScope(InventoryCampaignScope|array $scope): Builder
    {
        $query = Asset::query();
        $type = is_array($scope) ? $scope['scope_type'] : $scope->scope_type;
        $directionId = is_array($scope) ? ($scope['direction_id'] ?? null) : $scope->direction_id;
        $serviceId = is_array($scope) ? ($scope['service_id'] ?? null) : $scope->service_id;
        $locationId = is_array($scope) ? ($scope['location_id'] ?? null) : $scope->location_id;

        if ($type === 'direction') {
            $query->whereHas('activeAssignment', fn (Builder $assignment) => $assignment
                ->whereHas('service', fn (Builder $service) => $service->where('direction_id', $directionId))
                ->orWhereHas('agent.service', fn (Builder $service) => $service->where('direction_id', $directionId)));
        } elseif ($type === 'service') {
            $query->whereHas('activeAssignment', fn (Builder $assignment) => $assignment
                ->where('service_id', $serviceId)
                ->orWhereHas('agent', fn (Builder $agent) => $agent->where('service_id', $serviceId)));
        } elseif ($type === 'location') {
            $locationIds = collect([$locationId]);
            $frontier = collect([$locationId]);

            while ($frontier->isNotEmpty()) {
                $children = Location::query()->whereIn('parent_id', $frontier)->pluck('id');
                $locationIds = $locationIds->merge($children);
                $frontier = $children;
            }

            $query->whereIn('location_id', $locationIds->unique());
        }

        return $query;
    }
}
