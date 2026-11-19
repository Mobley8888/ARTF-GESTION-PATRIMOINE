<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['actor_id', 'validated_by', 'from_location_id', 'to_location_id', 'from_agent_id', 'to_agent_id', 'from_service_id', 'to_service_id', 'corrects_movement_id', 'reference', 'type', 'status', 'occurred_at', 'source', 'document_reference', 'reason', 'notes'])]
class StockMovement extends Model
{
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Un mouvement validé est immuable; toute correction doit être régularisée.'));
        static::deleting(fn (): never => throw new LogicException('Le journal des mouvements ne peut pas être supprimé.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function fromLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'from_location_id');
    }

    public function toLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'to_location_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class, 'movement_id');
    }

    public function correctionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_movement_id');
    }
}
