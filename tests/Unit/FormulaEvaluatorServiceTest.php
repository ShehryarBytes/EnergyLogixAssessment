<?php

namespace Tests\Unit;

use App\Services\Formula\FormulaEvaluatorService;
use App\Services\Formula\FormulaParserService;
use DivisionByZeroError;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class FormulaEvaluatorServiceTest extends TestCase
{
    private FormulaParserService $parser;
    private FormulaEvaluatorService $evaluator;

    protected function setUp(): void
    {
        $this->parser    = new FormulaParserService();
        $this->evaluator = new FormulaEvaluatorService();
    }

    public function test_simple_multiplication_produces_correct_result(): void
    {
        $ast    = $this->parser->parse('AnnualUsage * 0.05');
        $output = $this->evaluator->evaluate($ast, ['AnnualUsage' => 50000]);

        $this->assertEquals(2500.0, $output['result']);
    }

    public function test_result_array_contains_required_keys(): void
    {
        $ast    = $this->parser->parse('AnnualUsage * 0.05');
        $output = $this->evaluator->evaluate($ast, ['AnnualUsage' => 50000]);

        $this->assertArrayHasKey('result', $output);
        $this->assertArrayHasKey('steps', $output);
    }

    public function test_steps_array_contains_one_entry_per_binary_operation(): void
    {
        // AnnualUsage * 0.05 has one BinaryOperation → one step
        $ast    = $this->parser->parse('AnnualUsage * 0.05');
        $output = $this->evaluator->evaluate($ast, ['AnnualUsage' => 50000]);

        $this->assertCount(1, $output['steps']);

        // Compound formula has three BinaryOperations → three steps
        $ast    = $this->parser->parse('(AnnualUsage * 0.05) + (ContractLength * 100)');
        $output = $this->evaluator->evaluate($ast, ['AnnualUsage' => 50000, 'ContractLength' => 24]);

        $this->assertCount(3, $output['steps']);
    }

    public function test_step_strings_contain_the_operation_and_result(): void
    {
        $ast    = $this->parser->parse('AnnualUsage * 0.05');
        $output = $this->evaluator->evaluate($ast, ['AnnualUsage' => 50000]);

        $this->assertStringContainsString('AnnualUsage', $output['steps'][0]);
        $this->assertStringContainsString('*', $output['steps'][0]);
        $this->assertStringContainsString('2500.00', $output['steps'][0]);
    }

    public function test_compound_formula_variable_substitution_is_correct(): void
    {
        // (AnnualUsage * 0.05) + (ContractLength * 100)
        // = (50000 * 0.05) + (24 * 100)
        // = 2500 + 2400 = 4900
        $ast    = $this->parser->parse('(AnnualUsage * 0.05) + (ContractLength * 100)');
        $output = $this->evaluator->evaluate($ast, [
            'AnnualUsage'    => 50000,
            'ContractLength' => 24,
        ]);

        $this->assertEquals(4900.0, $output['result']);
    }

    public function test_all_four_system_variables_evaluate_correctly(): void
    {
        $ast    = $this->parser->parse('AnnualUsage + ContractValue + ContractLength + RiskScore');
        $output = $this->evaluator->evaluate($ast, [
            'AnnualUsage'    => 1000,
            'ContractValue'  => 200,
            'ContractLength' => 30,
            'RiskScore'      => 5,
        ]);

        $this->assertEquals(1235.0, $output['result']);
    }

    public function test_operator_precedence_is_preserved_during_evaluation(): void
    {
        // AnnualUsage + ContractLength * 100 should be AnnualUsage + (ContractLength * 100)
        // not (AnnualUsage + ContractLength) * 100
        $ast    = $this->parser->parse('AnnualUsage + ContractLength * 100');
        $output = $this->evaluator->evaluate($ast, [
            'AnnualUsage'    => 500,
            'ContractLength' => 24,
        ]);

        // Correct: 500 + (24 * 100) = 500 + 2400 = 2900
        // Wrong:   (500 + 24) * 100 = 52400
        $this->assertEquals(2900.0, $output['result']);
    }

    public function test_missing_variable_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/No value provided for variable 'AnnualUsage'/");

        $ast = $this->parser->parse('AnnualUsage * 0.05');
        $this->evaluator->evaluate($ast, []); // no values provided
    }

    public function test_division_by_zero_throws_error(): void
    {
        $this->expectException(DivisionByZeroError::class);

        $ast = $this->parser->parse('AnnualUsage / RiskScore');
        $this->evaluator->evaluate($ast, ['AnnualUsage' => 50000, 'RiskScore' => 0]);
    }

    public function test_sub_expression_labels_are_wrapped_in_parentheses_in_steps(): void
    {
        // The final addition step should show the two sub-results in parentheses
        $ast    = $this->parser->parse('(AnnualUsage * 0.05) + (ContractLength * 100)');
        $output = $this->evaluator->evaluate($ast, [
            'AnnualUsage'    => 50000,
            'ContractLength' => 24,
        ]);

        $additionStep = $output['steps'][2]; // third step is the final addition
        $this->assertStringStartsWith('(', $additionStep);
        $this->assertStringContainsString(') + (', $additionStep);
    }
}
