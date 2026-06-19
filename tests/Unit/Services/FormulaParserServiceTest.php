<?php

use App\Services\Formula\FormulaParserService;

beforeEach(function () {
    $this->parser = new FormulaParserService();
});

it('parses a simple addition of two numbers into a BinaryOperation node', function () {
    $ast = $this->parser->parse('1 + 2');

    expect($ast['type'])->toBe('BinaryOperation')
        ->and($ast['operator'])->toBe('+')
        ->and($ast['left']['type'])->toBe('Number')
        ->and($ast['left']['value'])->toBe(1.0)
        ->and($ast['right']['type'])->toBe('Number')
        ->and($ast['right']['value'])->toBe(2.0);
});

it('respects operator precedence — multiplication binds tighter than addition', function () {
    // AnnualUsage + ContractLength * 100 must parse as AnnualUsage + (ContractLength * 100)
    $ast = $this->parser->parse('AnnualUsage + ContractLength * 100');

    expect($ast['type'])->toBe('BinaryOperation')
        ->and($ast['operator'])->toBe('+')
        ->and($ast['left']['type'])->toBe('Variable')
        ->and($ast['left']['name'])->toBe('AnnualUsage')
        ->and($ast['right']['type'])->toBe('BinaryOperation')
        ->and($ast['right']['operator'])->toBe('*');
});

it('parentheses override natural operator precedence', function () {
    // (AnnualUsage + ContractLength) * 100 — addition is now the inner node
    $ast = $this->parser->parse('(AnnualUsage + ContractLength) * 100');

    expect($ast['type'])->toBe('BinaryOperation')
        ->and($ast['operator'])->toBe('*')
        ->and($ast['left']['type'])->toBe('BinaryOperation')
        ->and($ast['left']['operator'])->toBe('+')
        ->and($ast['right']['type'])->toBe('Number')
        ->and($ast['right']['value'])->toBe(100.0);
});

it('returns a Variable node for a single valid variable name', function () {
    $ast = $this->parser->parse('AnnualUsage');

    expect($ast['type'])->toBe('Variable')
        ->and($ast['name'])->toBe('AnnualUsage');
});

it('accepts all four system variable names without error', function () {
    foreach (['AnnualUsage', 'ContractValue', 'ContractLength', 'RiskScore'] as $name) {
        $ast = $this->parser->parse($name);
        expect($ast['type'])->toBe('Variable')
            ->and($ast['name'])->toBe($name);
    }
});

it('throws an exception naming the unknown variable', function () {
    expect(fn () => $this->parser->parse('GhostVar * 0.05'))
        ->toThrow(InvalidArgumentException::class, 'GhostVar');
});

it('throws a parse exception for an empty string', function () {
    expect(fn () => $this->parser->parse(''))
        ->toThrow(InvalidArgumentException::class);
});

it('throws a parse exception for mismatched parentheses', function () {
    expect(fn () => $this->parser->parse('(AnnualUsage * 0.05'))
        ->toThrow(InvalidArgumentException::class);
});

it('accepts extra allowed variable names passed as second argument', function () {
    $ast = $this->parser->parse('BaseCommission * 0.10', ['BaseCommission']);

    expect($ast['left']['name'])->toBe('BaseCommission');
});

it('produces a stable ast across multiple calls on the same instance', function () {
    $ast1 = $this->parser->parse('AnnualUsage * 0.05');
    $ast2 = $this->parser->parse('ContractLength + 100');

    expect($ast1['left']['name'])->toBe('AnnualUsage')
        ->and($ast2['left']['name'])->toBe('ContractLength');
});
