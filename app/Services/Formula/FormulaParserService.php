<?php

namespace App\Services\Formula;

use InvalidArgumentException;

/**
 * Converts a raw formula string into an Abstract Syntax Tree (AST).
 *
 * Two-stage process:
 *   1. Tokenizer   — reads the string character by character into typed tokens
 *   2. Parser      — consumes tokens using recursive descent, respecting operator precedence
 *
 * The returned AST is a plain PHP array, ready to be json_encode'd for storage.
 * eval() is intentionally never used.
 */
class FormulaParserService
{
    private const SYSTEM_VARIABLES = [
        'AnnualUsage',
        'ContractValue',
        'ContractLength',
        'RiskScore',
    ];

    /** Token stream for the current parse call */
    private array $tokens = [];
    private int $position = 0;

    /**
     * Parse a formula string into an AST.
     *
     * @param  string   $expression     The raw formula, e.g. "(AnnualUsage * 0.05) + (ContractLength * 100)"
     * @param  string[] $extraVariables Calculated variable names that are also valid in this formula
     * @return array    AST as a plain PHP array
     *
     * @throws InvalidArgumentException for syntax errors or unknown variable names
     */
    public function parse(string $expression, array $extraVariables = []): array
    {
        $expression = trim($expression);

        if ($expression === '') {
            throw new InvalidArgumentException('Formula expression cannot be empty.');
        }

        $this->tokens   = $this->tokenize($expression);
        $this->position = 0;

        $ast = $this->parseExpression();

        if ($this->hasMore()) {
            $token = $this->current();
            throw new InvalidArgumentException(
                "Unexpected token '{$token['value']}' — formula could not be fully parsed."
            );
        }

        $allowed = array_merge(self::SYSTEM_VARIABLES, $extraVariables);
        $this->validateVariables($ast, $allowed);

        return $ast;
    }

    // -------------------------------------------------------------------------
    // Stage 1: Tokenizer
    // -------------------------------------------------------------------------

    /**
     * Read the expression character by character and emit typed tokens.
     *
     * Token types: NUMBER, VARIABLE, OPERATOR (+, -, *, /), LPAREN, RPAREN
     *
     * @return array<int, array{type: string, value: string}>
     */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $i      = 0;
        $len    = strlen($expression);

        while ($i < $len) {
            $char = $expression[$i];

            if (ctype_space($char)) {
                $i++;
                continue;
            }

            // Numbers: integer or decimal (e.g. 100, 0.05, 1.5)
            if (ctype_digit($char) || $char === '.') {
                $start = $i;
                while ($i < $len && (ctype_digit($expression[$i]) || $expression[$i] === '.')) {
                    $i++;
                }
                $tokens[] = ['type' => 'NUMBER', 'value' => substr($expression, $start, $i - $start)];
                continue;
            }

            // Variable identifiers (e.g. AnnualUsage, BaseCommission)
            if (ctype_alpha($char) || $char === '_') {
                $start = $i;
                while ($i < $len && (ctype_alnum($expression[$i]) || $expression[$i] === '_')) {
                    $i++;
                }
                $tokens[] = ['type' => 'VARIABLE', 'value' => substr($expression, $start, $i - $start)];
                continue;
            }

            if (in_array($char, ['+', '-', '*', '/'], true)) {
                $tokens[] = ['type' => 'OPERATOR', 'value' => $char];
                $i++;
                continue;
            }

            if ($char === '(') {
                $tokens[] = ['type' => 'LPAREN', 'value' => '('];
                $i++;
                continue;
            }

            if ($char === ')') {
                $tokens[] = ['type' => 'RPAREN', 'value' => ')'];
                $i++;
                continue;
            }

            throw new InvalidArgumentException("Unexpected character '{$char}' in formula.");
        }

        return $tokens;
    }

    // -------------------------------------------------------------------------
    // Stage 2: Recursive Descent Parser
    //
    // Grammar (enforces * / before + -):
    //   expression → term   ( ('+' | '-') term   )*
    //   term       → factor ( ('*' | '/') factor )*
    //   factor     → NUMBER | VARIABLE | '(' expression ')'
    // -------------------------------------------------------------------------

    private function parseExpression(): array
    {
        $left = $this->parseTerm();

        while ($this->hasMore()
            && $this->current()['type'] === 'OPERATOR'
            && in_array($this->current()['value'], ['+', '-'], true)
        ) {
            $operator = $this->consume()['value'];
            $right    = $this->parseTerm();
            $left     = [
                'type'     => 'BinaryOperation',
                'operator' => $operator,
                'left'     => $left,
                'right'    => $right,
            ];
        }

        return $left;
    }

    private function parseTerm(): array
    {
        $left = $this->parseFactor();

        while ($this->hasMore()
            && $this->current()['type'] === 'OPERATOR'
            && in_array($this->current()['value'], ['*', '/'], true)
        ) {
            $operator = $this->consume()['value'];
            $right    = $this->parseFactor();
            $left     = [
                'type'     => 'BinaryOperation',
                'operator' => $operator,
                'left'     => $left,
                'right'    => $right,
            ];
        }

        return $left;
    }

    private function parseFactor(): array
    {
        if (!$this->hasMore()) {
            throw new InvalidArgumentException(
                'Unexpected end of formula — expected a number, variable, or opening parenthesis.'
            );
        }

        $token = $this->current();

        if ($token['type'] === 'NUMBER') {
            $this->consume();
            return ['type' => 'Number', 'value' => (float) $token['value']];
        }

        if ($token['type'] === 'VARIABLE') {
            $this->consume();
            return ['type' => 'Variable', 'name' => $token['value']];
        }

        if ($token['type'] === 'LPAREN') {
            $this->consume(); // consume '('
            $expr = $this->parseExpression();

            if (!$this->hasMore() || $this->current()['type'] !== 'RPAREN') {
                throw new InvalidArgumentException('Missing closing parenthesis.');
            }

            $this->consume(); // consume ')'
            return $expr;
        }

        throw new InvalidArgumentException(
            "Unexpected token '{$token['value']}' — expected a number, variable, or opening parenthesis."
        );
    }

    // -------------------------------------------------------------------------
    // Variable validation — walk the completed AST
    // -------------------------------------------------------------------------

    private function validateVariables(array $node, array $allowed): void
    {
        if ($node['type'] === 'Variable') {
            if (!in_array($node['name'], $allowed, true)) {
                throw new InvalidArgumentException(
                    "Unknown variable '{$node['name']}'. Allowed: " . implode(', ', $allowed) . '.'
                );
            }
            return;
        }

        if ($node['type'] === 'BinaryOperation') {
            $this->validateVariables($node['left'], $allowed);
            $this->validateVariables($node['right'], $allowed);
        }
    }

    // -------------------------------------------------------------------------
    // Token stream helpers
    // -------------------------------------------------------------------------

    private function hasMore(): bool
    {
        return $this->position < count($this->tokens);
    }

    private function current(): array
    {
        return $this->tokens[$this->position];
    }

    private function consume(): array
    {
        return $this->tokens[$this->position++];
    }
}
