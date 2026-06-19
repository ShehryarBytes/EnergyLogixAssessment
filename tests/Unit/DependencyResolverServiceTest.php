<?php

namespace Tests\Unit;

use App\Services\Formula\DependencyResolverService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DependencyResolverServiceTest extends TestCase
{
    private DependencyResolverService $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DependencyResolverService();
    }

    public function test_empty_definitions_returns_empty_array(): void
    {
        $this->assertSame([], $this->resolver->resolve([]));
    }

    public function test_single_variable_with_no_inter_dependencies_is_returned(): void
    {
        $defs   = [['name' => 'BaseCommission', 'expression' => 'AnnualUsage * 0.05']];
        $sorted = $this->resolver->resolve($defs);

        $this->assertCount(1, $sorted);
        $this->assertEquals('BaseCommission', $sorted[0]['name']);
    }

    public function test_independent_variables_all_appear_in_result(): void
    {
        // Neither variable depends on the other — both reference only system vars
        $defs = [
            ['name' => 'BaseCommission',  'expression' => 'AnnualUsage * 0.05'],
            ['name' => 'BonusCommission', 'expression' => 'AnnualUsage * 0.10'],
        ];
        $sorted = $this->resolver->resolve($defs);
        $names  = array_column($sorted, 'name');

        $this->assertCount(2, $sorted);
        $this->assertContains('BaseCommission', $names);
        $this->assertContains('BonusCommission', $names);
    }

    public function test_linear_dependency_chain_is_sorted_correctly(): void
    {
        // BonusCommission depends on BaseCommission → BaseCommission must come first
        $defs = [
            ['name' => 'BonusCommission', 'expression' => 'BaseCommission * 0.10'],
            ['name' => 'BaseCommission',  'expression' => 'AnnualUsage * 0.05'],
        ];
        $sorted = $this->resolver->resolve($defs);
        $names  = array_column($sorted, 'name');

        $this->assertLessThan(
            array_search('BonusCommission', $names),
            array_search('BaseCommission', $names),
            'BaseCommission must appear before BonusCommission'
        );
    }

    public function test_three_level_chain_is_fully_ordered(): void
    {
        // FinalCommission depends on BonusCommission which depends on BaseCommission
        // Correct order: BaseCommission → BonusCommission → FinalCommission
        $defs = [
            ['name' => 'FinalCommission', 'expression' => 'BaseCommission + BonusCommission'],
            ['name' => 'BonusCommission', 'expression' => 'BaseCommission * 0.10'],
            ['name' => 'BaseCommission',  'expression' => 'AnnualUsage * 0.05'],
        ];
        $sorted = $this->resolver->resolve($defs);
        $names  = array_column($sorted, 'name');

        $baseIdx  = array_search('BaseCommission', $names);
        $bonusIdx = array_search('BonusCommission', $names);
        $finalIdx = array_search('FinalCommission', $names);

        $this->assertLessThan($bonusIdx, $baseIdx,  'BaseCommission must come before BonusCommission');
        $this->assertLessThan($finalIdx, $bonusIdx, 'BonusCommission must come before FinalCommission');
    }

    public function test_direct_circular_dependency_throws_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/[Cc]ircular/');

        $defs = [
            ['name' => 'Alpha', 'expression' => 'Beta * 2'],
            ['name' => 'Beta',  'expression' => 'Alpha * 2'],
        ];

        $this->resolver->resolve($defs);
    }

    public function test_indirect_circular_dependency_throws_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/[Cc]ircular/');

        // A → B → C → A
        $defs = [
            ['name' => 'A', 'expression' => 'C * 1'],
            ['name' => 'B', 'expression' => 'A * 1'],
            ['name' => 'C', 'expression' => 'B * 1'],
        ];

        $this->resolver->resolve($defs);
    }

    public function test_resolved_definitions_retain_all_original_fields(): void
    {
        // The resolver must return the full original definition arrays, not just names
        $defs = [
            ['name' => 'Base',  'expression' => 'AnnualUsage * 0.05', 'sort_order' => 0],
            ['name' => 'Bonus', 'expression' => 'Base * 0.10',        'sort_order' => 0],
        ];
        $sorted = $this->resolver->resolve($defs);

        $this->assertArrayHasKey('sort_order', $sorted[0]);
        $this->assertArrayHasKey('expression', $sorted[0]);
    }

    public function test_exception_message_names_the_cycled_variables(): void
    {
        $defs = [
            ['name' => 'Alpha', 'expression' => 'Beta * 2'],
            ['name' => 'Beta',  'expression' => 'Alpha * 2'],
        ];

        try {
            $this->resolver->resolve($defs);
            $this->fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Alpha', $e->getMessage());
            $this->assertStringContainsString('Beta', $e->getMessage());
        }
    }
}
