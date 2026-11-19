<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

#[Fillable([
    'category_id',
    'location_id',
    'created_by',
    'updated_by',
    'asset_code',
    'designation',
    'description',
    'serial_number',
    'characteristics',
    'acquired_on',
    'acquisition_value',
    'manufacturer',
    'brand',
    'model',
    'manufacturer_reference',
    'commissioned_on',
    'acquisition_mode',
    'supplier',
    'invoice_reference',
    'delivery_reference',
    'acquisition_notes',
    'usage_status',
    'condition',
    'status',
])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'characteristics' => 'array',
            'acquired_on' => 'date',
            'commissioned_on' => 'date',
            'acquisition_value' => 'decimal:2',
            'condition' => AssetCondition::class,
            'status' => AssetStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Asset $asset): void {
            $amount = $asset->getAttributes()['acquisition_value'] ?? null;

            if ($amount !== null && preg_match('/^\d{1,16}(?:\.\d{1,2})?$/D', (string) $amount) !== 1) {
                throw ValidationException::withMessages([
                    'acquisition_value' => 'La valeur doit être positive ou nulle et respecter le format DECIMAL(18,2).',
                ]);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function locationHistory(): HasMany
    {
        return $this->hasMany(AssetLocationHistory::class)->orderBy('moved_at');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->orderByDesc('assigned_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(AssetDocument::class)->latest();
    }

    public function inventoryItems(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class)->latest('reported_at');
    }

    public function maintenanceSchedules(): HasMany
    {
        return $this->hasMany(MaintenanceSchedule::class)->latest('next_due_on');
    }

    public function stockMovementLines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('ended_at')->latestOfMany('assigned_at');
    }

    public function scopeAtSite(Builder $query, int|string $siteId): Builder
    {
        return $query->whereHas('location', function (Builder $location) use ($siteId): void {
            $location->whereKey($siteId)
                ->orWhere('locations.parent_id', $siteId)
                ->orWhereHas('parent', fn (Builder $parent) => $parent->where('parent_id', $siteId));
        });
    }
}
