<?php

namespace App\Http\Controllers\Api;

use App\Enums\AnalysisStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ImpactAnalysisResource;
use App\Jobs\RunImpactAnalysisJob;
use App\Models\ImpactAnalysis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SimulationController extends Controller
{
    /**
     * POST /api/v1/simulation/run
     * Dispatch an impact analysis job and return immediately with 202 Accepted.
     * The client polls GET /simulation/{id} until status is complete or failed.
     * Requires manage-formulas gate (admin only).
     */
    public function run(Request $request): JsonResponse
    {
        $this->authorize('manage-formulas');

        $request->validate([
            'formula_id' => ['required', 'integer', 'exists:formulas,id'],
        ]);

        $analysis = ImpactAnalysis::create([
            'formula_id'   => $request->formula_id,
            'triggered_by' => auth()->user()->email,
            'status'       => AnalysisStatus::Pending,
        ]);

        RunImpactAnalysisJob::dispatch($analysis->id);

        return response()->json([
            'id'     => $analysis->id,
            'status' => $analysis->status->value,
        ], 202);
    }

    /**
     * GET /api/v1/simulation/{id}
     * Return the current state of an impact analysis.
     * Frontend polls every two seconds until status is complete or failed.
     * Both admin and viewer roles can poll.
     */
    public function show(string $id): ImpactAnalysisResource
    {
        $analysis = ImpactAnalysis::with('formula')->findOrFail($id);

        return new ImpactAnalysisResource($analysis);
    }
}
