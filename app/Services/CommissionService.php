<?php

namespace App\Services;

use App\Enums\FormulaStatus;
use App\Models\CommissionCalculation;
use App\Models\Contract;
use App\Models\Formula;
use App\Services\Formula\FormulaEvaluatorService;
use RuntimeException;

class CommissionService
{
    public function __construct(
        private readonly FormulaEvaluatorService $evaluator,
    ) {}

    /**
     * Calculate commission for a contract using the currently active formula.
     *
     * Evaluation order:
     *   1. Resolve each formula_variable in sort_order sequence, adding each result to the
     *      running values array so subsequent variables can reference earlier ones.
     *   2. Evaluate the main formula AST with the now-complete values array.
     *
     * The resulting CommissionCalculation record is append-only — it must never be modified
     * after creation. input_values stores the raw contract snapshot; calculation_steps stores
     * the full ordered audit trail.
     *
     * @throws RuntimeException if no formula is active or the formula has no compiled AST.
     */
    public function calculate(int $contractId): CommissionCalculation
    {
        $formula = Formula::with('variables')
            ->where('status', FormulaStatus::Active->value)
            ->first();

        if ($formula === null) {
            throw new RuntimeException(
                'No active formula is configured. Activate a formula before running calculations.'
            );
        }

        if (empty($formula->ast_json)) {
            throw new RuntimeException(
                "The active formula \"{$formula->name}\" does not have a compiled AST. " .
                'Re-create it through the API to generate the AST.'
            );
        }

        $contract = Contract::findOrFail($contractId);

        // Map contract columns to the formula variable names expected by the evaluator
        $values = [
            'AnnualUsage'    => (float) $contract->annual_usage,
            'ContractValue'  => (float) $contract->contract_value,
            'ContractLength' => (float) $contract->contract_length,
            'RiskScore'      => (float) $contract->risk_score,
        ];

        // Snapshot the raw contract inputs before we add calculated variables
        $inputSnapshot = $values;

        $allSteps = [];

        // Step 1 — Evaluate calculated variables in dependency-resolved sort_order
        foreach ($formula->variables->sortBy('sort_order') as $variable) {
            $varResult = $this->evaluator->evaluate($variable->ast_json, $values);

            // Label each step with the variable name so the audit trail is self-explanatory
            foreach ($varResult['steps'] as $step) {
                $allSteps[] = "{$variable->variable_name}: {$step}";
            }

            // Make the resolved value available to subsequent variables and the main expression
            $values[$variable->variable_name] = $varResult['result'];
        }

        // Step 2 — Evaluate the main formula with the fully resolved values array
        $mainResult = $this->evaluator->evaluate($formula->ast_json, $values);
        $allSteps   = array_merge($allSteps, $mainResult['steps']);

        // Step 3 — Persist the immutable audit record
        return CommissionCalculation::create([
            'contract_id'       => $contract->id,
            'formula_id'        => $formula->id,
            'input_values'      => $inputSnapshot,
            'calculation_steps' => $allSteps,
            'result'            => $mainResult['result'],
            'calculated_at'     => now(),
        ]);
    }
}
