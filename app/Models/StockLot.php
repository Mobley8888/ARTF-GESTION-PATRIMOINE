<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['article_id', 'location_id', 'source_movement_id', 'reference', 'received_on', 'quantity_received', 'quantity_available', 'unit_cost', 'source', 'document_reference'])]
class StockLot extends Model
{
    protected function casts(): array
    {
        return ['received_on' => 'date', 'quantity_received' => 'decimal:3', 'quantity_available' => 'decimal:3', 'unit_cost' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::deleting(fn (): never => throw new LogicException('Les lots de stock sont conservés dans la traçabilité.'));
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function sourceMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'source_movement_id');
    }

    public function movementLines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class, 'lot_id');
    }
}
