<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContractResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'customer_name'   => $this->customer_name,
            'annual_usage'    => $this->annual_usage,
            'contract_value'  => $this->contract_value,
            'contract_length' => $this->contract_length,
            'risk_score'      => $this->risk_score,
        ];
    }
}
