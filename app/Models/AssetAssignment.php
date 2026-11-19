<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'asset_id',
    'movement_type',
    'agent_id',
    'service_id',
    'assigned_by',
    'ended_by',
    'assigned_at',
    'ended_at',
    'reason',
    'end_reason',
    'observations',
    'received_by_name',
    'return_condition',
    'return_observations',
])]
class AssetAssignment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (AssetAssignment $assignment): void {
            $allowedClosureFields = ['ended_by', 'ended_at', 'end_reason', 'received_by_name', 'return_condition', 'return_observations'];
            $dirtyFields = array_keys($assignment->getDirty());

            if ($assignment->getOriginal('ended_at') !== null || ! in_array('ended_at', $dirtyFields, true) || array_diff($dirtyFields, $allowedClosureFields) !== []) {
                throw new LogicException('Une affectation historique ne peut être modifiée que pour enregistrer sa clôture contrôlée.');
            }
        });

        static::deleting(function (): never {
            throw new LogicException('L’historique des affectations ne peut pas être supprimé.');
        });
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function ender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }
}
