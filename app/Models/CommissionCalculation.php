<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionCalculation extends Model
{
    // No updated_at — this record is append-only and must never be modified after creation.
    public $timestamps = false;

    protected $fillable = [
        'contract_id',
        'formula_id',
        'input_values',
        'calculation_steps',
        'result',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'input_values'      => 'array',
            'calculation_steps' => 'array',
            'result'            => 'decimal:4',
            'calculated_at'     => 'datetime',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }
}
