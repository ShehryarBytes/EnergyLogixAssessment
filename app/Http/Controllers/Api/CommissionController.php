<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommissionCalculationResource;
use App\Models\CommissionCalculation;
use App\Services\CommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use RuntimeException;

class CommissionController extends Controller
{
    public function __construct(
        private readonly CommissionService $commissionService,
    ) {}

    /**
     * POST /api/v1/commission/calculate
     * Calculate commission for a contract using the active formula.
     * Both admin and viewer roles may trigger calculations.
     */
    public function calculate(Request $request): JsonResponse|CommissionCalculationResource
    {
        $request->validate([
            'contract_id' => ['required', 'integer', 'exists:contracts,id'],
        ]);

        try {
            $calculation = $this->commissionService->calculate((int) $request->contract_id);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Load relationships so the resource can render contract and formula details
        $calculation->load(['contract', 'formula']);

        return new CommissionCalculationResource($calculation);
    }

    /**
     * GET /api/v1/commission/history
     * Paginated list of all calculations, newest first.
     */
    public function history(): AnonymousResourceCollection
    {
        $calculations = CommissionCalculation::with(['contract', 'formula'])
            ->orderByDesc('calculated_at')
            ->paginate(20);

        return CommissionCalculationResource::collection($calculations);
    }

    /**
     * GET /api/v1/commission/history/{id}
     * Single calculation with every audit step in order.
     */
    public function historyShow(string $id): CommissionCalculationResource
    {
        $calculation = CommissionCalculation::with(['contract', 'formula'])
            ->findOrFail($id);

        return new CommissionCalculationResource($calculation);
    }
}
