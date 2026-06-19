<?php

use App\Services\Formula\DependencyResolverService;

beforeEach(function () {
    $this->resolver = new DependencyResolverService();
});

it('a single variable with no inter-dependencies resolves correctly', function () {
    $sorted = $this->resolver->resolve([
        ['name' => 'BaseCommission', 'expression' => 'AnnualUsage * 0.05'],
    ]);

    expect($sorted)->toHaveCount(1)
        ->and($sorted[0]['name'])->toBe('BaseCommission');
});

it('two independent variables both appear in the result', function () {
    $sorted = $this->resolver->resolve([
        ['name' => 'BaseCommission',  'expression' => 'AnnualUsage * 0.05'],
        ['name' => 'BonusCommission', 'expression' => 'AnnualUsage * 0.10'],
    ]);

    $names = array_column($sorted, 'name');

    expect($sorted)->toHaveCount(2)
        ->and($names)->toContain('BaseCommission')
        ->and($names)->toContain('BonusCommission');
});

it('a chain where B depends on A returns A before B', function () {
    $sorted = $this->resolver->resolve([
        ['name' => 'BonusCommission', 'expression' => 'BaseCommission * 0.10'],
        ['name' => 'BaseCommission',  'expression' => 'AnnualUsage * 0.05'],
    ]);

    $names    = array_column($sorted, 'name');
    $baseIdx  = array_search('BaseCommission', $names);
    $bonusIdx = array_search('BonusCommission', $names);

    expect($baseIdx)->toBeLessThan($bonusIdx);
});

it('a three-level chain resolves in correct dependency order', function () {
    $sorted = $this->resolver->resolve([
        ['name' => 'FinalCommission', 'expression' => 'BaseCommission + BonusCommission'],
        ['name' => 'BonusCommission', 'expression' => 'BaseCommission * 0.10'],
        ['name' => 'BaseCommission',  'expression' => 'AnnualUsage * 0.05'],
    ]);

    $names    = array_column($sorted, 'name');
    $baseIdx  = array_search('BaseCommission', $names);
    $bonusIdx = array_search('BonusCommission', $names);
    $finalIdx = array_search('FinalCommission', $names);

    expect($baseIdx)->toBeLessThan($bonusIdx)
        ->and($bonusIdx)->toBeLessThan($finalIdx);
});

it('a direct circular dependency between two variables throws an exception', function () {
    expect(fn () => $this->resolver->resolve([
        ['name' => 'Alpha', 'expression' => 'Beta * 2'],
        ['name' => 'Beta',  'expression' => 'Alpha * 2'],
    ]))->toThrow(RuntimeException::class);
});

it('an indirect cycle across three variables throws an exception', function () {
    expect(fn () => $this->resolver->resolve([
        ['name' => 'A', 'expression' => 'C * 1'],
        ['name' => 'B', 'expression' => 'A * 1'],
        ['name' => 'C', 'expression' => 'B * 1'],
    ]))->toThrow(RuntimeException::class);
});

it('exception message names the variables involved in the cycle', function () {
    expect(fn () => $this->resolver->resolve([
        ['name' => 'Alpha', 'expression' => 'Beta * 2'],
        ['name' => 'Beta',  'expression' => 'Alpha * 2'],
    ]))->toThrow(RuntimeException::class, 'Alpha');
});

it('returns original definition arrays in full, not just names', function () {
    $sorted = $this->resolver->resolve([
        ['name' => 'Base',  'expression' => 'AnnualUsage * 0.05', 'sort_order' => 0],
        ['name' => 'Bonus', 'expression' => 'Base * 0.10',        'sort_order' => 0],
    ]);

    expect($sorted[0])->toHaveKey('expression')
        ->and($sorted[0])->toHaveKey('sort_order');
});
