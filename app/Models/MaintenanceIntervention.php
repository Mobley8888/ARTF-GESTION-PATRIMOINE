<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['request_id', 'schedule_id', 'performed_by', 'created_by', 'reference', 'status', 'planned_at', 'started_at', 'completed_at', 'diagnosis', 'work_performed', 'parts_used', 'labor_cost', 'parts_cost', 'observations', 'report'])]
class MaintenanceIntervention extends Model
{
    protected function casts(): array
    {
        return ['planned_at' => 'datetime', 'started_at' => 'datetime', 'completed_at' => 'datetime', 'labor_cost' => 'decimal:2', 'parts_cost' => 'decimal:2'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'request_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'schedule_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'performed_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(MaintenanceHistory::class, 'intervention_id')->orderByDesc('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(MaintenanceDocument::class, 'intervention_id')->orderByDesc('created_at');
    }

    public function getTotalCostAttribute(): string
    {
        return number_format((float) $this->labor_cost + (float) $this->parts_cost, 2, '.', '');
    }
}
