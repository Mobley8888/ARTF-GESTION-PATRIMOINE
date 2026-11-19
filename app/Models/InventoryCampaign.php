<?php

namespace App\Models;

use App\Enums\InventoryCampaignStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['reference', 'title', 'description', 'starts_on', 'ends_on', 'status', 'responsible_user_id', 'created_by', 'validated_by', 'closed_by', 'launched_at', 'validated_at', 'closed_at'])]
class InventoryCampaign extends Model
{
    protected function casts(): array
    {
        return [
            'status' => InventoryCampaignStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
            'launched_at' => 'datetime',
            'validated_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (InventoryCampaign $campaign): void {
            if ($campaign->getOriginal('status') === InventoryCampaignStatus::Closed->value) {
                throw new LogicException('Une campagne clôturée est immuable.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Une campagne d’inventaire ne peut pas être supprimée.');
        });
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(InventoryCampaignScope::class, 'campaign_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(InventoryTask::class, 'campaign_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'campaign_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(InventoryReview::class, 'campaign_id')->orderBy('reviewed_at');
    }
}
