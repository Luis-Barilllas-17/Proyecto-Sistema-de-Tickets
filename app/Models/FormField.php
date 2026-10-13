<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    public const TYPES = [
        'text' => 'Texto corto',
        'textarea' => 'Texto largo',
        'number' => 'Número',
        'date' => 'Fecha',
        'select' => 'Lista desplegable',
        'radio' => 'Opciones múltiples',
        'checkbox' => 'Casilla de verificación',
        'file' => 'Archivo',
    ];

    protected $fillable = [
        'ticket_form_id', 'name', 'label', 'type', 'placeholder', 'help_text', 'default_value', 'options',
        'sort_order', 'is_required', 'is_visible', 'is_readonly', 'is_active', 'min_value', 'max_value',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_visible' => 'boolean',
            'is_readonly' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(TicketForm::class, 'ticket_form_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? str($this->type)->headline()->toString();
    }
}
