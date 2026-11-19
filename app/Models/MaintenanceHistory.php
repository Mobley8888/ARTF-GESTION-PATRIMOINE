<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_id', 'schedule_id', 'intervention_id', 'actor_id', 'event_type', 'from_status', 'to_status', 'description', 'changes'])]
class MaintenanceHistory extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new \LogicException('Un événement de maintenance est immuable.'));
        static::deleting(fn (): never => throw new \LogicException('L’historique de maintenance ne peut pas être supprimé.'));
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'request_id');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(MaintenanceSchedule::class, 'schedule_id');
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(MaintenanceIntervention::class, 'intervention_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
