<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\Service;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetAssignmentService
{
    public function __construct(private readonly StockMovementService $stock) {}

    public function assign(
        Asset $asset,
        ?int $agentId,
        ?int $serviceId,
        User $actor,
        string $assignedAt,
        string $reason,
        ?string $observations,
    ): AssetAssignment {
        return DB::transaction(function () use ($asset, $agentId, $serviceId, $actor, $assignedAt, $reason, $observations): AssetAssignment {
            $lockedAsset = Asset::query()->lockForUpdate()->findOrFail($asset->getKey());

            $this->stock->assertAssetAssignable($lockedAsset);

            if ($lockedAsset->assignments()->whereNull('ended_at')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Ce bien possède déjà une affectation active.',
                ]);
            }

            $assignee = $this->assignee($agentId, $serviceId);

            $assignment = $lockedAsset->assignments()->create([
                'movement_type' => 'assignment',
                ...$assignee,
                'assigned_by' => $actor->getKey(),
                'assigned_at' => CarbonImmutable::parse($assignedAt),
                'reason' => $reason,
                'observations' => $observations,
            ]);
            $lockedAsset->forceFill(['stock_state' => 'assigned'])->save();
            $this->stock->recordAssetMovement($lockedAsset, 'asset_assignment', $actor, $reason, $lockedAsset->location_id, $lockedAsset->location_id, toAgentId: $assignee['agent_id'], toServiceId: $assignee['service_id'], occurredAt: $assignedAt);

            return $assignment;
        }, attempts: 3);
    }

    public function transfer(
        Asset $asset,
        ?int $agentId,
        ?int $serviceId,
        User $actor,
        string $transferredAt,
        string $reason,
        ?string $observations,
    ): AssetAssignment {
        return DB::transaction(function () use ($asset, $agentId, $serviceId, $actor, $transferredAt, $reason, $observations): AssetAssignment {
            $lockedAsset = Asset::query()->lockForUpdate()->findOrFail($asset->getKey());
            $current = $lockedAsset->assignments()->whereNull('ended_at')->lockForUpdate()->first();

            if (! $current) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Aucune affectation active à transférer.',
                ]);
            }

            $assignee = $this->assignee($agentId, $serviceId);
            $transferDate = CarbonImmutable::parse($transferredAt);

            if ($transferDate->lessThan($current->assigned_at)) {
                throw ValidationException::withMessages([
                    'assigned_at' => 'La date du transfert ne peut pas précéder la prise en charge actuelle.',
                ]);
            }

            if (
                $current->agent_id === ($assignee['agent_id'] ?? null)
                && $current->service_id === ($assignee['service_id'] ?? null)
            ) {
                throw ValidationException::withMessages([
                    'agent_id' => 'Le destinataire doit être différent du détenteur actuel.',
                ]);
            }

            $current->forceFill([
                'ended_by' => $actor->getKey(),
                'ended_at' => $transferDate,
                'end_reason' => $reason,
            ])->save();

            $transfer = $lockedAsset->assignments()->create([
                'movement_type' => 'transfer',
                ...$assignee,
                'assigned_by' => $actor->getKey(),
                'assigned_at' => $transferDate,
                'reason' => $reason,
                'observations' => $observations,
            ]);
            $lockedAsset->forceFill(['stock_state' => 'assigned'])->save();
            $this->stock->recordAssetMovement($lockedAsset, 'asset_transfer', $actor, $reason, $lockedAsset->location_id, $lockedAsset->location_id, $current->agent_id, $assignee['agent_id'], $current->service_id, $assignee['service_id'], occurredAt: $transferredAt);

            return $transfer;
        }, attempts: 3);
    }

    public function restitute(
        Asset $asset,
        User $actor,
        string $returnedAt,
        string $reason,
        ?string $receivedByName = null,
        ?string $returnCondition = null,
        ?string $returnObservations = null,
    ): AssetAssignment {
        return DB::transaction(function () use ($asset, $actor, $returnedAt, $reason, $receivedByName, $returnCondition, $returnObservations): AssetAssignment {
            $lockedAsset = Asset::query()->lockForUpdate()->findOrFail($asset->getKey());
            $current = $lockedAsset->assignments()->whereNull('ended_at')->lockForUpdate()->first();

            if (! $current) {
                throw ValidationException::withMessages([
                    'asset_id' => 'Ce bien ne possède aucune affectation active à restituer.',
                ]);
            }

            $returnDate = CarbonImmutable::parse($returnedAt);

            if ($returnDate->lessThan($current->assigned_at)) {
                throw ValidationException::withMessages([
                    'returned_at' => 'La date de restitution ne peut pas précéder la prise en charge.',
                ]);
            }

            $current->forceFill([
                'ended_by' => $actor->getKey(),
                'ended_at' => $returnDate,
                'end_reason' => $reason,
                'received_by_name' => $receivedByName,
                'return_condition' => $returnCondition,
                'return_observations' => $returnObservations,
            ])->save();

            $lockedAsset->forceFill(['stock_state' => 'awaiting_inspection'])->save();
            $this->stock->recordAssetMovement($lockedAsset, 'asset_return', $actor, $reason, $lockedAsset->location_id, $lockedAsset->location_id, $current->agent_id, null, $current->service_id, null, occurredAt: $returnedAt);

            return $current;
        }, attempts: 3);
    }

    private function assignee(?int $agentId, ?int $serviceId): array
    {
        if (($agentId === null) === ($serviceId === null)) {
            throw ValidationException::withMessages([
                'agent_id' => 'Sélectionnez un seul agent ou un seul service.',
            ]);
        }

        if ($agentId !== null) {
            $agent = Agent::query()->lockForUpdate()->findOrFail($agentId);

            if (! $agent->is_active) {
                throw ValidationException::withMessages([
                    'agent_id' => 'Un agent inactif ne peut pas recevoir de bien.',
                ]);
            }

            return ['agent_id' => $agent->getKey(), 'service_id' => null];
        }

        $service = Service::query()->lockForUpdate()->findOrFail($serviceId);

        if (! $service->is_active) {
            throw ValidationException::withMessages([
                'service_id' => 'Un service inactif ne peut pas recevoir de bien.',
            ]);
        }

        return ['agent_id' => null, 'service_id' => $service->getKey()];
    }
}
