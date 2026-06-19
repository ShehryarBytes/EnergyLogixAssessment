<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommissionCalculationResource;
use App\Models\CommissionCalculation;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditController extends Controller
{
    /**
     * GET /api/v1/audit
     * Paginated commission calculation history for the audit trail.
     */
    public function index(): AnonymousResourceCollection
    {
        return CommissionCalculationResource::collection(
            CommissionCalculation::with(['contract', 'formula'])
                ->orderByDesc('calculated_at')
                ->paginate(20)
        );
    }

    /**
     * GET /api/v1/audit/{id}
     * Single calculation with full input snapshot, steps, and formula detail.
     */
    public function show(string $id): CommissionCalculationResource
    {
        return new CommissionCalculationResource(
            CommissionCalculation::with(['contract', 'formula'])->findOrFail($id)
        );
    }
}
