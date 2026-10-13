<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevelopmentAccessProfile extends Model
{
    protected $fillable = [
        'development_request_id', 'name', 'description', 'permissions', 'data_scope',
        'includes_sensitive_data', 'third_party_access', 'created_by_id',
    ];

    protected function casts(): array
    {
        return ['includes_sensitive_data' => 'boolean', 'third_party_access' => 'boolean'];
    }

    public function developmentRequest(): BelongsTo
    {
        return $this->belongsTo(DevelopmentRequest::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
