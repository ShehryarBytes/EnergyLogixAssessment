<?php

namespace App\Models;

use App\Enums\AnalysisStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImpactAnalysis extends Model
{
    protected $fillable = [
        'formula_id',
        'triggered_by',
        'affected_contracts',
        'current_total',
        'new_total',
        'difference',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status'             => AnalysisStatus::class,
            'current_total'      => 'decimal:4',
            'new_total'          => 'decimal:4',
            'difference'         => 'decimal:4',
            'affected_contracts' => 'integer',
        ];
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }
}
