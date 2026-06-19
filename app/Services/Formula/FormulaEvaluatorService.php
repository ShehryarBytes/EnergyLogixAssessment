<?php

namespace App\Services\Formula;

use DivisionByZeroError;
use InvalidArgumentException;

/**
 * Walks an AST produced by FormulaParserService and evaluates it against
 * a set of input values, recording every operation as a human-readable step.
 *
 * The steps array is stored verbatim in commission_calculations.calculation_steps
 * so that any calculation can be fully reconstructed later.
 */
class FormulaEvaluatorService
{
    /**
     * Evaluate an AST with the provided variable values.
     *
     * @param  array  $ast    AST from FormulaParserService::parse()
     * @param  array  $values Associative map of variable name → numeric value
     *                        e.g. ['AnnualUsage' => 284000, 'ContractLength' => 36]
     * @return array{result: float, steps: string[]}
     */
    public function evaluate(array $ast, array $values): array
    {
        $steps = [];
        ['value' => $result] = $this->walkNode($ast, $values, $steps);

        return [
            'result' => $result,
            'steps'  => $steps,
        ];
    }

    // -------------------------------------------------------------------------
    // AST walker
    // -------------------------------------------------------------------------

    /**
     * Recursively evaluate a node, appending a step string for every operation.
     *
     * Returns an array with:
     *   'value'  — the numeric result of this node
     *   'label'  — a human-readable representation used to build parent step strings
     *
     * @return array{value: float, label: string}
     */
    private function walkNode(array $node, array $values, array &$steps): array
    {
        return match ($node['type']) {
            'Number'          => $this->walkNumber($node),
            'Variable'        => $this->walkVariable($node, $values),
            'BinaryOperation' => $this->walkBinaryOperation($node, $values, $steps),
            default           => throw new InvalidArgumentException(
                "Unknown AST node type '{$node['type']}'."
            ),
        };
    }

    private function walkNumber(array $node): array
    {
        $value = (float) $node['value'];
        // Show the number as-is (no trailing zeros for clean step descriptions)
        $label = rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
        return ['value' => $value, 'label' => $label];
    }

    private function walkVariable(array $node, array $values): array
    {
        $name = $node['name'];

        if (!array_key_exists($name, $values)) {
            throw new InvalidArgumentException(
                "No value provided for variable '{$name}'."
            );
        }

        return ['value' => (float) $values[$name], 'label' => $name];
    }

    private function walkBinaryOperation(array $node, array $values, array &$steps): array
    {
        $left  = $this->walkNode($node['left'],  $values, $steps);
        $right = $this->walkNode($node['right'], $values, $steps);

        $result = match ($node['operator']) {
            '+' => $left['value'] + $right['value'],
            '-' => $left['value'] - $right['value'],
            '*' => $left['value'] * $right['value'],
            '/' => $right['value'] == 0
                ? throw new DivisionByZeroError('Division by zero encountered during formula evaluation.')
                : $left['value'] / $right['value'],
            default => throw new InvalidArgumentException(
                "Unknown operator '{$node['operator']}'."
            ),
        };

        // Wrap sub-expression labels in parentheses so the step reads like maths
        $leftLabel  = $node['left']['type']  === 'BinaryOperation'
            ? "({$left['label']})"
            : $left['label'];
        $rightLabel = $node['right']['type'] === 'BinaryOperation'
            ? "({$right['label']})"
            : $right['label'];

        $resultStr = number_format($result, 2, '.', '');
        $steps[]   = "{$leftLabel} {$node['operator']} {$rightLabel} = {$resultStr}";

        return ['value' => $result, 'label' => $resultStr];
    }
}
