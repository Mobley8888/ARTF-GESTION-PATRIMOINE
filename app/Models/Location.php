<?php

namespace App\Models;

use App\Enums\LocationType;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['parent_id', 'type', 'code', 'name', 'address', 'surface_m2', 'description', 'is_active'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
            'surface_m2' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Location $location): void {
            $parentTypes = $location->type->parentTypes();

            if ($parentTypes === [] && $location->parent_id !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Un site ne peut pas avoir de lieu parent.',
                ]);
            }

            if ($parentTypes !== []) {
                if ($location->parent_id === null || (string) $location->getKey() === (string) $location->parent_id) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Ce type de lieu doit avoir un lieu parent.',
                    ]);
                }

                $parent = self::query()->find($location->parent_id);

                if (! $parent || ! $parent->is_active || ! $location->type->acceptsParent($parent->type)) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Le lieu parent doit être actif et respecter la hiérarchie définie.',
                    ]);
                }

                if ($parent->type->isSite() && $parent->parent_id !== null) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Le site parent doit être un site principal.',
                    ]);
                }

                if ($location->type->isLocal() && $parent->type->isBuilding()) {
                    $site = $parent->parent;

                    if (! $site || ! $site->is_active || ! $site->type->isSite() || $site->parent_id !== null) {
                        throw ValidationException::withMessages([
                            'parent_id' => 'Le bâtiment parent doit dépendre d’un site principal actif.',
                        ]);
                    }
                }

                if ($location->type->isBuilding() && ! $parent->type->isSite()) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Un bâtiment doit dépendre d’un site principal.',
                    ]);
                }
            }

            if (
                $location->exists
                && $location->type->isLocal()
                && $location->children()->exists()
            ) {
                throw ValidationException::withMessages([
                    'type' => 'Un local ne peut pas contenir un autre lieu.',
                ]);
            }

            if (
                $location->exists
                && ($location->isDirty('type') || $location->isDirty('parent_id'))
                && (
                    $location->children()->exists()
                    || $location->assets()->withTrashed()->exists()
                    || $location->sourceHistory()->exists()
                    || $location->destinationHistory()->exists()
                )
            ) {
                throw ValidationException::withMessages([
                    'parent_id' => 'La hiérarchie d’un lieu utilisé, historique ou ayant des enfants ne peut pas être modifiée.',
                ]);
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function sourceHistory(): HasMany
    {
        return $this->hasMany(AssetLocationHistory::class, 'from_location_id');
    }

    public function destinationHistory(): HasMany
    {
        return $this->hasMany(AssetLocationHistory::class, 'to_location_id');
    }
}
