<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionCalculationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'contract'    => [
                'id'            => $this->contract->id,
                'customer_name' => $this->contract->customer_name,
            ],
            'formula'     => [
                'id'      => $this->formula->id,
                'name'    => $this->formula->name,
                'version' => $this->formula->version,
            ],
            // Raw contract values at the time of calculation — never changes after creation
            'input_values'      => $this->input_values,
            // Ordered audit trail: one string per AST operation, prefixed with variable
            // name for calculated variables e.g. "BaseCommission: AnnualUsage * 0.05 = 14200.00"
            'calculation_steps' => $this->calculation_steps,
            'result'            => number_format((float) $this->result, 4, '.', ''),
            'calculated_at'     => $this->calculated_at->toIso8601String(),
        ];
    }
}
