<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

#[Fillable(['parent_id', 'code', 'name', 'description', 'is_active'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            if ($category->parent_id === null) {
                return;
            }

            if ($category->exists && (string) $category->getKey() === (string) $category->parent_id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Une catégorie ne peut pas être sa propre parente.',
                ]);
            }

            $parent = self::query()->find($category->parent_id);

            if (! $parent || ! $parent->is_active || $parent->parent_id !== null) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Une sous-catégorie doit dépendre d’une catégorie racine active.',
                ]);
            }

            if ($category->exists && $category->isDirty('parent_id') && $category->children()->exists()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Une catégorie ayant des sous-catégories ne peut pas être déplacée.',
                ]);
            }

            if (
                $category->exists
                && $category->isDirty('parent_id')
                && $category->assets()->withTrashed()->exists()
            ) {
                throw ValidationException::withMessages([
                    'parent_id' => 'La catégorie d’un bien référencé ne peut pas être déplacée.',
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
}
