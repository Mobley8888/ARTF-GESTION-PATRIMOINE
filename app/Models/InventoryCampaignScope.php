<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['campaign_id', 'scope_type', 'direction_id', 'service_id', 'location_id'])]
class InventoryCampaignScope extends Model
{
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(InventoryCampaign::class, 'campaign_id');
    }

    public function direction(): BelongsTo
    {
        return $this->belongsTo(Direction::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function task(): HasOne
    {
        return $this->hasOne(InventoryTask::class, 'scope_id');
    }

    public function label(): string
    {
        return match ($this->scope_type) {
            'global' => 'Patrimoine global',
            'direction' => 'Direction : '.($this->direction?->name ?? 'inconnue'),
            'service' => 'Service : '.($this->service?->name ?? 'inconnu'),
            'location' => 'Localisation : '.($this->location?->name ?? 'inconnue'),
            default => 'Périmètre non défini',
        };
    }
}
