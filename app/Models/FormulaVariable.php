<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormulaVariable extends Model
{
    protected $fillable = [
        'formula_id',
        'variable_name',
        'expression',
        'ast_json',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'ast_json'   => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }
}
