<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImpactAnalysisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'formula'    => [
                'id'      => $this->formula->id,
                'name'    => $this->formula->name,
                'version' => $this->formula->version,
            ],
            'status'             => $this->status->value,
            'affected_contracts' => $this->affected_contracts,
            // Decimal fields are null while the job is pending; formatted once complete
            'current_total'      => $this->formatDecimal($this->current_total),
            'new_total'          => $this->formatDecimal($this->new_total),
            'difference'         => $this->formatDecimal($this->difference),
            'triggered_by'       => $this->triggered_by,
            'created_at'         => $this->created_at->toIso8601String(),
        ];
    }

    private function formatDecimal(mixed $value): ?string
    {
        return $value !== null ? number_format((float) $value, 4, '.', '') : null;
    }
}
