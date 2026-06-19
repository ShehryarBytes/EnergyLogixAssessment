<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contract extends Model
{
    protected $fillable = [
        'customer_name',
        'annual_usage',
        'contract_value',
        'contract_length',
        'risk_score',
    ];

    protected function casts(): array
    {
        return [
            'annual_usage'    => 'decimal:4',
            'contract_value'  => 'decimal:4',
            'risk_score'      => 'decimal:2',
            'contract_length' => 'integer',
        ];
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(CommissionCalculation::class);
    }
}
