<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['asset_id', 'assigned_agent_id', 'created_by', 'reference', 'title', 'description', 'frequency', 'provider_name', 'next_due_on', 'last_completed_on', 'is_active'])]
class MaintenanceSchedule extends Model
{
    protected function casts(): array
    {
        return ['next_due_on' => 'date', 'last_completed_on' => 'date', 'is_active' => 'boolean'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'schedule_id');
    }

    public function interventions(): HasMany
    {
        return $this->hasMany(MaintenanceIntervention::class, 'schedule_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(MaintenanceHistory::class, 'schedule_id')->orderByDesc('created_at');
    }
}
