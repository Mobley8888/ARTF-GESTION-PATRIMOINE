<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

#[Fillable(['category_id', 'sku', 'name', 'description', 'unit', 'minimum_stock', 'is_active'])]
class Article extends Model
{
    protected function casts(): array
    {
        return ['minimum_stock' => 'decimal:3', 'is_active' => 'boolean'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class)->orderBy('received_on')->orderBy('id');
    }

    public function movementLines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class);
    }

    public function scopeWithAvailability(Builder $query): Builder
    {
        return $query->withSum(['lots as quantity_available' => fn (Builder $lots) => $lots->where('quantity_available', '>', 0)], 'quantity_available');
    }
}
