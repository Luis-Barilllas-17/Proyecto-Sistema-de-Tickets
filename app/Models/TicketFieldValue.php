<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketFieldValue extends Model
{
    protected $fillable = ['ticket_id', 'form_field_id', 'value', 'value_json'];

    protected function casts(): array
    {
        return ['value_json' => 'array'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(FormField::class, 'form_field_id');
    }

    public function getDisplayValueAttribute(): string
    {
        if (is_array($this->value_json)) {
            return implode(', ', array_map('strval', $this->value_json));
        }

        return (string) $this->value;
    }
}
