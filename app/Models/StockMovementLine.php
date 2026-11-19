<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['movement_id', 'article_id', 'asset_id', 'lot_id', 'destination_lot_id', 'from_location_id', 'to_location_id', 'from_agent_id', 'to_agent_id', 'from_service_id', 'to_service_id', 'quantity', 'unit_cost'])]
class StockMovementLine extends Model
{
    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_cost' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Une ligne de mouvement validée est immuable.'));
        static::deleting(fn (): never => throw new LogicException('Une ligne de mouvement ne peut pas être supprimée.'));
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'movement_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'lot_id');
    }

    public function destinationLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class, 'destination_lot_id');
    }

    public function fromAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'from_agent_id');
    }

    public function toAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'to_agent_id');
    }

    public function fromService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'from_service_id');
    }

    public function toService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'to_service_id');
    }
}
