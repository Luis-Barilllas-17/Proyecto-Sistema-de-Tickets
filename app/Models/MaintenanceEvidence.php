<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceEvidence extends Model
{
    protected $table = 'maintenance_evidences';

    protected $fillable = [
        'maintenance_order_id', 'uploaded_by_id', 'disk', 'path', 'original_name',
        'mime_type', 'size', 'sha256', 'description',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(MaintenanceOrder::class, 'maintenance_order_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }

    public function getHumanSizeAttribute(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : number_format(max(1, $this->size / 1024), 0).' KB';
    }
}
