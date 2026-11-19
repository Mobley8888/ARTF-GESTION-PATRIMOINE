<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;
use LogicException;

#[Fillable(['asset_id', 'from_location_id', 'to_location_id', 'recorded_by', 'moved_at', 'reason', 'notes'])]
class AssetLocationHistory extends Model
{
    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AssetLocationHistory $history): void {
            $fromLocationId = $history->from_location_id;
            $toLocationId = $history->to_location_id;
            $sameLocation = $fromLocationId !== null
                && $toLocationId !== null
                && (string) $fromLocationId === (string) $toLocationId;

            if (
                ($fromLocationId === null && $toLocationId === null)
                || $sameLocation
                || trim((string) $history->reason) === ''
                || mb_strlen(trim((string) $history->reason)) > 120
            ) {
                throw ValidationException::withMessages([
                    'history' => 'Un événement doit préciser un motif et un changement de lieu.',
                ]);
            }
        });

        static::updating(function (): never {
            throw new LogicException('L’historique de localisation est immuable.');
        });

        static::deleting(function (): never {
            throw new LogicException('L’historique de localisation ne peut pas être supprimé.');
        });
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
