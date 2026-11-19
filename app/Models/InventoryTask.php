<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['campaign_id', 'scope_id', 'agent_id', 'assigned_by', 'status', 'due_on', 'submitted_at'])]
class InventoryTask extends Model
{
    protected function casts(): array
    {
        return ['due_on' => 'date', 'submitted_at' => 'datetime'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(InventoryCampaign::class);
    }

    public function scope(): BelongsTo
    {
        return $this->belongsTo(InventoryCampaignScope::class, 'scope_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'task_id');
    }
}
