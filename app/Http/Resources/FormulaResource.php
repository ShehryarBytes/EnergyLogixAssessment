<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormulaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'version'      => $this->version,
            'status'       => $this->status->value,
            'expression'   => $this->expression,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'created_by'   => $this->created_by,
            'created_at'   => $this->created_at->toIso8601String(),
            // Only included when the relationship has been eager-loaded
            'variables'    => FormulaVariableResource::collection($this->whenLoaded('variables')),
        ];
    }
}
