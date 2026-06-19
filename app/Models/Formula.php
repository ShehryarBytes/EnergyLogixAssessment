<?php

namespace App\Models;

use App\Enums\FormulaStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Formula extends Model
{
    protected $fillable = [
        'name',
        'version',
        'expression',
        'ast_json',
        'status',
        'activated_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'ast_json'     => 'array',
            'status'       => FormulaStatus::class,
            'activated_at' => 'datetime',
            'version'      => 'integer',
        ];
    }

    public function variables(): HasMany
    {
        return $this->hasMany(FormulaVariable::class)->orderBy('sort_order');
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(CommissionCalculation::class);
    }

    public function impactAnalyses(): HasMany
    {
        return $this->hasMany(ImpactAnalysis::class);
    }
}
