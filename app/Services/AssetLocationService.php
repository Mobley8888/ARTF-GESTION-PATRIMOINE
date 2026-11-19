<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetLocationHistory;
use App\Models\Location;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssetLocationService
{
    public function __construct(private readonly StockMovementService $stock) {}

    public function move(Asset $asset, ?Location $destination, User $actor, string $reason, ?string $movedAt = null, ?string $notes = null, bool $registerStockMovement = true): ?AssetLocationHistory
    {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 120) {
            throw ValidationException::withMessages([
                'reason' => 'Le motif du changement de lieu est obligatoire.',
            ]);
        }

        return DB::transaction(function () use ($asset, $destination, $actor, $reason, $movedAt, $notes, $registerStockMovement): ?AssetLocationHistory {
            $currentAsset = Asset::query()
                ->lockForUpdate()
                ->findOrFail($asset->getKey());

            $destinationId = $destination?->getKey();
            $movementDate = $movedAt ? CarbonImmutable::parse($movedAt) : CarbonImmutable::now();

            if ($destinationId !== null) {
                $destination = Location::query()
                    ->lockForUpdate()
                    ->findOrFail($destinationId);

                if (! $destination->is_active) {
                    throw ValidationException::withMessages([
                        'location_id' => 'Un bien ne peut pas être déplacé vers un lieu inactif.',
                    ]);
                }

                $previousMovement = $currentAsset->locationHistory()->reorder()->orderByDesc('moved_at')->first();
                if ($previousMovement && $movementDate->lessThan($previousMovement->moved_at)) {
                    throw ValidationException::withMessages([
                        'moved_at' => 'La date du déplacement ne peut pas précéder le dernier mouvement physique enregistré.',
                    ]);
                }
            }

            if ((string) $currentAsset->location_id === (string) $destinationId) {
                return null;
            }

            $history = AssetLocationHistory::query()->create([
                'asset_id' => $currentAsset->getKey(),
                'from_location_id' => $currentAsset->location_id,
                'to_location_id' => $destinationId,
                'recorded_by' => $actor->getKey(),
                'moved_at' => $movementDate,
                'reason' => $reason,
                'notes' => $notes,
            ]);

            $currentAsset->forceFill([
                'location_id' => $destinationId,
                'updated_by' => $actor->getKey(),
            ])->save();

            if ($registerStockMovement) {
                $this->stock->recordAssetMovement($currentAsset, 'physical_transfer', $actor, $reason, $history->from_location_id, $history->to_location_id, occurredAt: $movementDate->toDateTimeString());
            }

            return $history;
        });
    }
}
