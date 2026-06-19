<?php

use App\Services\Formula\FormulaEvaluatorService;
use App\Services\Formula\FormulaParserService;

beforeEach(function () {
    $this->parser    = new FormulaParserService();
    $this->evaluator = new FormulaEvaluatorService();
});

it('AnnualUsage * 0.05 with AnnualUsage = 100000 returns exactly 5000', function () {
    $ast    = $this->parser->parse('AnnualUsage * 0.05');
    $result = $this->evaluator->evaluate($ast, ['AnnualUsage' => 100000]);

    expect($result['result'])->toBe(5000.0);
});

it('a compound addition expression returns the mathematically correct result', function () {
    // (AnnualUsage * 0.05) + (ContractLength * 100)
    // = (50000 * 0.05) + (24 * 100) = 2500 + 2400 = 4900
    $ast    = $this->parser->parse('(AnnualUsage * 0.05) + (ContractLength * 100)');
    $result = $this->evaluator->evaluate($ast, [
        'AnnualUsage'    => 50000,
        'ContractLength' => 24,
    ]);

    expect($result['result'])->toBe(4900.0);
});

it('the steps array contains at least one entry per operation in the formula', function () {
    // Three binary operations → three steps
    $ast    = $this->parser->parse('(AnnualUsage * 0.05) + (ContractLength * 100)');
    $result = $this->evaluator->evaluate($ast, [
        'AnnualUsage'    => 50000,
        'ContractLength' => 24,
    ]);

    expect($result['steps'])->toHaveCount(3)
        ->and($result['steps'][0])->toContain('AnnualUsage')
        ->and($result['steps'][0])->toContain('*');
});

it('passing a variable value of zero does not cause a division error', function () {
    $ast    = $this->parser->parse('AnnualUsage + RiskScore');
    $result = $this->evaluator->evaluate($ast, ['AnnualUsage' => 1000, 'RiskScore' => 0]);

    expect($result['result'])->toBe(1000.0);
});

it('all four system variables can be substituted correctly in one formula', function () {
    $ast    = $this->parser->parse('AnnualUsage + ContractValue + ContractLength + RiskScore');
    $result = $this->evaluator->evaluate($ast, [
        'AnnualUsage'    => 1000,
        'ContractValue'  => 200,
        'ContractLength' => 30,
        'RiskScore'      => 5,
    ]);

    expect($result['result'])->toBe(1235.0);
});

it('throws when a required variable value is missing from the input array', function () {
    $ast = $this->parser->parse('AnnualUsage * 0.05');

    expect(fn () => $this->evaluator->evaluate($ast, []))
        ->toThrow(InvalidArgumentException::class, 'AnnualUsage');
});

it('throws DivisionByZeroError when dividing by a zero variable', function () {
    $ast = $this->parser->parse('AnnualUsage / RiskScore');

    expect(fn () => $this->evaluator->evaluate($ast, ['AnnualUsage' => 5000, 'RiskScore' => 0]))
        ->toThrow(DivisionByZeroError::class);
});

it('step strings wrap sub-expression results in parentheses for readability', function () {
    $ast    = $this->parser->parse('(AnnualUsage * 0.05) + (ContractLength * 100)');
    $result = $this->evaluator->evaluate($ast, [
        'AnnualUsage'    => 50000,
        'ContractLength' => 24,
    ]);

    // The final addition step should read "(2500.00) + (2400.00) = 4900.00"
    expect($result['steps'][2])->toStartWith('(')
        ->and($result['steps'][2])->toContain(') + (');
});
