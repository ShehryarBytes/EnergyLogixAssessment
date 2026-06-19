<?php

namespace App\Services;

use App\Enums\AnalysisStatus;
use App\Enums\FormulaStatus;
use App\Models\Contract;
use App\Models\Formula;
use App\Models\ImpactAnalysis;
use App\Services\Formula\FormulaEvaluatorService;
use Throwable;

/**
 * Dry-run formula evaluation across all contracts for impact analysis.
 *
 * This service intentionally never writes to commission_calculations.
 * It reads contracts, evaluates both the simulated formula and the currently
 * active formula, accumulates totals, and updates the ImpactAnalysis record.
 */
class SimulationService
{
    public function __construct(
        private readonly FormulaEvaluatorService $evaluator,
    ) {}

    /**
     * Run an impact analysis for the formula referenced by $analysisId.
     *
     * @throws Throwable — re-thrown after marking the analysis as failed.
     */
    public function run(int $analysisId): void
    {
        $analysis = ImpactAnalysis::findOrFail($analysisId);

        $simulatedFormula = Formula::with('variables')->findOrFail($analysis->formula_id);

        // Fetch the currently active formula for the "before" side of the comparison.
        // Null means no formula is active, so the current total is treated as zero.
        $activeFormula = Formula::with('variables')
            ->where('status', FormulaStatus::Active->value)
            ->first();

        $contracts = Contract::all();

        try {
            $newTotal     = 0.0;
            $currentTotal = 0.0;

            foreach ($contracts as $contract) {
                $newTotal += $this->evaluateFormula($simulatedFormula, $contract);

                if ($activeFormula !== null && !empty($activeFormula->ast_json)) {
                    $currentTotal += $this->evaluateFormula($activeFormula, $contract);
                }
            }

            $analysis->update([
                'affected_contracts' => $contracts->count(),
                'current_total'      => $currentTotal,
                'new_total'          => $newTotal,
                'difference'         => $newTotal - $currentTotal,
                'status'             => AnalysisStatus::Complete,
            ]);
        } catch (Throwable $e) {
            // Mark failed before re-throwing so the polling endpoint reflects reality
            $analysis->update(['status' => AnalysisStatus::Failed]);
            throw $e;
        }
    }

    /**
     * Evaluate a formula against a single contract without creating any audit records.
     * Mirrors the evaluation logic in CommissionService exactly.
     */
    private function evaluateFormula(Formula $formula, Contract $contract): float
    {
        if (empty($formula->ast_json)) {
            // Treat formulas without a compiled AST as contributing zero to totals
            return 0.0;
        }

        $values = [
            'AnnualUsage'    => (float) $contract->annual_usage,
            'ContractValue'  => (float) $contract->contract_value,
            'ContractLength' => (float) $contract->contract_length,
            'RiskScore'      => (float) $contract->risk_score,
        ];

        // Resolve calculated variables in dependency order before the main expression
        foreach ($formula->variables->sortBy('sort_order') as $variable) {
            $result                          = $this->evaluator->evaluate($variable->ast_json, $values);
            $values[$variable->variable_name] = $result['result'];
        }

        return $this->evaluator->evaluate($formula->ast_json, $values)['result'];
    }
}
