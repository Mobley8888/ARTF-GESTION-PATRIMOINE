<?php

namespace App\Models;

use App\Enums\InventoryItemStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

#[Fillable(['campaign_id', 'task_id', 'asset_id', 'expected_snapshot', 'observed_asset_code', 'observed_designation', 'observed_serial_number', 'observed_location_id', 'observed_agent_id', 'observed_service_id', 'physical_condition', 'status', 'physical_anomaly', 'administrative_anomaly', 'observations', 'observed_by', 'observed_at'])]
class InventoryItem extends Model
{
    protected function casts(): array
    {
        return ['expected_snapshot' => 'array', 'status' => InventoryItemStatus::class, 'observed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (InventoryItem $item): void {
            if ($item->campaign()->value('status') === 'closed') {
                throw new LogicException('Un constat d’une campagne clôturée ne peut pas être modifié.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('Un constat d’inventaire ne peut pas être supprimé.');
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(InventoryCampaign::class, 'campaign_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(InventoryTask::class, 'task_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function observedLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'observed_location_id');
    }

    public function observedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'observed_agent_id');
    }

    public function observedService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'observed_service_id');
    }

    public function observer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'observed_by');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(InventoryEvidence::class, 'inventory_item_id');
    }
}
