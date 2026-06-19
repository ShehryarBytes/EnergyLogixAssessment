<?php

namespace App\Services\Formula;

use RuntimeException;

/**
 * Resolves the execution order of calculated formula variables using Kahn's
 * topological sort algorithm on their dependency graph.
 *
 * System variables (AnnualUsage, ContractValue, etc.) are always available as
 * external inputs and are excluded from the graph — only the relationships
 * between administrator-defined calculated variables are considered.
 */
class DependencyResolverService
{
    /**
     * Sort variable definitions into a safe execution order.
     *
     * @param  array[] $definitions  Each element must have at least 'name' and 'expression' keys.
     * @return array[] The same definitions, re-ordered so every variable's dependencies come first.
     *
     * @throws RuntimeException if a circular dependency is detected.
     */
    public function resolve(array $definitions): array
    {
        if (empty($definitions)) {
            return [];
        }

        $calculatedNames = array_column($definitions, 'name');

        // Build the directed graph for Kahn's algorithm.
        // An edge from A → B means "A must be evaluated before B".
        // in-degree of a node = how many unsatisfied dependencies it still has.
        $adjacency = array_fill_keys($calculatedNames, []);  // node → [dependents]
        $inDegree  = array_fill_keys($calculatedNames, 0);   // node → unsatisfied dependency count

        foreach ($definitions as $def) {
            $referencedVars = $this->extractVariableNames($def['expression']);

            foreach ($referencedVars as $ref) {
                // Only wire up edges between calculated variables; system vars are always available.
                if (in_array($ref, $calculatedNames, true) && $ref !== $def['name']) {
                    $adjacency[$ref][] = $def['name'];
                    $inDegree[$def['name']]++;
                }
            }
        }

        // Kahn's algorithm: process nodes with no remaining dependencies first.
        $queue  = array_keys(array_filter($inDegree, fn ($d) => $d === 0));
        $sorted = [];

        while ($queue) {
            $current = array_shift($queue);
            $sorted[] = $current;

            foreach ($adjacency[$current] as $dependent) {
                $inDegree[$dependent]--;
                if ($inDegree[$dependent] === 0) {
                    $queue[] = $dependent;
                }
            }
        }

        // If not all nodes were sorted, remaining nodes form a cycle.
        if (count($sorted) !== count($definitions)) {
            $cycled = array_diff($calculatedNames, $sorted);
            sort($cycled);
            throw new RuntimeException(
                'Circular dependency detected among calculated variables: '
                . implode(', ', $cycled) . '.'
            );
        }

        $indexed = array_column($definitions, null, 'name');

        return array_map(fn ($name) => $indexed[$name], $sorted);
    }

    /**
     * Extract all identifier tokens from a formula expression string.
     * Used to discover which variables an expression depends on without a full parse.
     *
     * @return string[]
     */
    private function extractVariableNames(string $expression): array
    {
        preg_match_all('/[A-Za-z_][A-Za-z0-9_]*/', $expression, $matches);
        return $matches[0];
    }
}
