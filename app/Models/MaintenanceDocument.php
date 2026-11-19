<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_id', 'intervention_id', 'uploaded_by', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class MaintenanceDocument extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['size' => 'integer', 'created_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'request_id');
    }

    public function intervention(): BelongsTo
    {
        return $this->belongsTo(MaintenanceIntervention::class, 'intervention_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
