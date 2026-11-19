<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['campaign_id', 'reviewed_by', 'step', 'decision', 'observations', 'reviewed_at'])]
class InventoryReview extends Model
{
    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Une décision de contrôle d’inventaire est immuable.');
        });

        static::deleting(function (): never {
            throw new LogicException('Une décision de contrôle d’inventaire ne peut pas être supprimée.');
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(InventoryCampaign::class, 'campaign_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
