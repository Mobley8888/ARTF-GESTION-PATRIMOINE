<?php

namespace App\Models;

use App\Enums\MaintenanceRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['asset_id', 'schedule_id', 'reported_by', 'assigned_agent_id', 'assigned_by', 'validated_by', 'reference', 'type', 'title', 'description', 'priority', 'status', 'reported_at', 'planned_at', 'provider_name', 'technical_diagnosis', 'resolution_notes', 'observations', 'validated_at', 'completed_at'])]
class MaintenanceRequest extends Model
{
    protected function casts(): array
    {
        return ['status' => MaintenanceRequestStatus::class, 'reported_at' => 'datetime', 'planned_at' => 'datetime', 'validated_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'schedule_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'assigned_agent_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function intervention(): HasOne
    {
        return $this->hasOne(MaintenanceIntervention::class, 'request_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(MaintenanceHistory::class, 'request_id')->orderByDesc('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MaintenanceDocument::class, 'request_id')->orderByDesc('created_at');
    }
}
