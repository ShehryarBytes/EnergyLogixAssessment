<?php

namespace Tests\Unit;

use App\Services\Formula\FormulaParserService;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FormulaParserServiceTest extends TestCase
{
    private FormulaParserService $parser;

    protected function setUp(): void
    {
        $this->parser = new FormulaParserService();
    }

    public function test_simple_multiplication_produces_binary_operation_node(): void
    {
        $ast = $this->parser->parse('AnnualUsage * 0.05');

        $this->assertEquals('BinaryOperation', $ast['type']);
        $this->assertEquals('*', $ast['operator']);
        $this->assertEquals('Variable', $ast['left']['type']);
        $this->assertEquals('AnnualUsage', $ast['left']['name']);
        $this->assertEquals('Number', $ast['right']['type']);
        $this->assertEquals(0.05, $ast['right']['value']);
    }

    public function test_operator_precedence_binds_multiplication_tighter_than_addition(): void
    {
        // Without parentheses: AnnualUsage + ContractLength * 100
        // Expected tree: Add(AnnualUsage, Mul(ContractLength, 100))
        // i.e. the top-level node is + and the right child is the multiplication
        $ast = $this->parser->parse('AnnualUsage + ContractLength * 100');

        $this->assertEquals('BinaryOperation', $ast['type']);
        $this->assertEquals('+', $ast['operator']);

        $this->assertEquals('Variable', $ast['left']['type']);
        $this->assertEquals('AnnualUsage', $ast['left']['name']);

        $this->assertEquals('BinaryOperation', $ast['right']['type']);
        $this->assertEquals('*', $ast['right']['operator']);
        $this->assertEquals('ContractLength', $ast['right']['left']['name']);
        $this->assertEquals(100.0, $ast['right']['right']['value']);
    }

    public function test_parentheses_override_operator_precedence(): void
    {
        // With parentheses: (AnnualUsage + ContractLength) * 100
        // Expected tree: Mul(Add(AnnualUsage, ContractLength), 100)
        // i.e. the top-level node is * and the left child is the addition
        $ast = $this->parser->parse('(AnnualUsage + ContractLength) * 100');

        $this->assertEquals('BinaryOperation', $ast['type']);
        $this->assertEquals('*', $ast['operator']);

        $this->assertEquals('BinaryOperation', $ast['left']['type']);
        $this->assertEquals('+', $ast['left']['operator']);
        $this->assertEquals('AnnualUsage', $ast['left']['left']['name']);
        $this->assertEquals('ContractLength', $ast['left']['right']['name']);

        $this->assertEquals('Number', $ast['right']['type']);
        $this->assertEquals(100.0, $ast['right']['value']);
    }

    public function test_compound_formula_from_claude_md_parses_correctly(): void
    {
        $ast = $this->parser->parse('(AnnualUsage * 0.05) + (ContractLength * 100)');

        // Top level should be addition
        $this->assertEquals('BinaryOperation', $ast['type']);
        $this->assertEquals('+', $ast['operator']);

        // Left subtree: AnnualUsage * 0.05
        $this->assertEquals('BinaryOperation', $ast['left']['type']);
        $this->assertEquals('*', $ast['left']['operator']);

        // Right subtree: ContractLength * 100
        $this->assertEquals('BinaryOperation', $ast['right']['type']);
        $this->assertEquals('*', $ast['right']['operator']);
    }

    public function test_extra_allowed_variables_are_accepted(): void
    {
        // Calculated variables (e.g. BaseCommission) must be passed as extra allowlist
        $ast = $this->parser->parse('BaseCommission * 0.10', ['BaseCommission']);

        $this->assertEquals('BinaryOperation', $ast['type']);
        $this->assertEquals('Variable', $ast['left']['type']);
        $this->assertEquals('BaseCommission', $ast['left']['name']);
    }

    public function test_unknown_variable_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/Unknown variable 'GhostVar'/");

        $this->parser->parse('GhostVar * 0.05');
    }

    public function test_consecutive_operators_throw_exception(): void
    {
        // "AnnualUsage * * 0.05" is a syntax error — the parser expects a factor after the first *
        $this->expectException(InvalidArgumentException::class);

        $this->parser->parse('AnnualUsage * * 0.05');
    }

    public function test_empty_expression_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/empty/i');

        $this->parser->parse('');
    }

    public function test_unclosed_parenthesis_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/parenthesis/i');

        $this->parser->parse('(AnnualUsage * 0.05');
    }

    public function test_parser_can_be_called_multiple_times_without_state_leak(): void
    {
        // Second call must not be influenced by first call's token stream
        $ast1 = $this->parser->parse('AnnualUsage * 0.05');
        $ast2 = $this->parser->parse('ContractLength + 100');

        $this->assertEquals('AnnualUsage', $ast1['left']['name']);
        $this->assertEquals('ContractLength', $ast2['left']['name']);
    }
}
