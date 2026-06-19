<?php

use App\Models\CommissionCalculation;
use App\Models\Contract;
use App\Models\Formula;
use App\Models\User;
use App\Services\Formula\FormulaParserService;
use Laravel\Sanctum\Sanctum;

// Unique name — avoids conflict with formulaWithAst() in FormulaApiTest.php
function commissionActiveFormula(): Formula
{
    $parser = new FormulaParserService();

    return Formula::create([
        'name'         => 'Commission Test Formula',
        'version'      => 1,
        'expression'   => 'AnnualUsage * 0.05',
        'ast_json'     => $parser->parse('AnnualUsage * 0.05'),
        'status'       => 'active',
        'activated_at' => now(),
        'created_by'   => 'test@energylogix.com',
    ]);
}

function commissionTestContract(): Contract
{
    return Contract::create([
        'customer_name'   => 'Test Customer Ltd',
        'annual_usage'    => '100000.0000',
        'contract_value'  => '50000.0000',
        'contract_length' => 24,
        'risk_score'      => '3.50',
    ]);
}

beforeEach(function () {
    $this->user     = User::factory()->create(['role' => 'viewer']);
    $this->formula  = commissionActiveFormula();
    $this->contract = commissionTestContract();
});

// -------------------------------------------------------------------------
// calculate
// -------------------------------------------------------------------------

it('an authenticated user can calculate commission and gets a successful response', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/commission/calculate', ['contract_id' => $this->contract->id])
        ->assertSuccessful() // 201 — resource was just created
        ->assertJsonStructure(['data' => ['result', 'formula', 'contract', 'calculation_steps']]);
});

it('calculating with no active formula returns 422 with a descriptive error', function () {
    $this->formula->update(['status' => 'archived']);

    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/commission/calculate', ['contract_id' => $this->contract->id]);

    $response->assertStatus(422)
        ->assertJsonStructure(['message']);

    expect($response->json('message'))->toContain('active formula');
});

it('the result in the response is a string decimal not a PHP float', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson('/api/v1/commission/calculate', ['contract_id' => $this->contract->id]);

    // AnnualUsage * 0.05 = 100000 * 0.05 = 5000 → formatted as "5000.0000"
    expect($response->json('data.result'))->toBeString()
        ->and($response->json('data.result'))->toBe('5000.0000');
});

it('a calculation creates a record in commission_calculations', function () {
    Sanctum::actingAs($this->user);

    $before = CommissionCalculation::count();

    $this->postJson('/api/v1/commission/calculate', ['contract_id' => $this->contract->id])
        ->assertSuccessful();

    expect(CommissionCalculation::count())->toBe($before + 1);
});

it('the input_values stored in the record match the contract actual values', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/commission/calculate', ['contract_id' => $this->contract->id]);

    $calc = CommissionCalculation::latest()->first();

    expect($calc->input_values)->toHaveKeys(['AnnualUsage', 'ContractValue', 'ContractLength', 'RiskScore'])
        ->and((float) $calc->input_values['AnnualUsage'])->toEqual((float) $this->contract->annual_usage)
        ->and((float) $calc->input_values['ContractLength'])->toEqual((float) $this->contract->contract_length);
});

it('the calculation_steps stored in the record is a non-empty array', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/commission/calculate', ['contract_id' => $this->contract->id]);

    $calc = CommissionCalculation::latest()->first();

    expect($calc->calculation_steps)->toBeArray()->not->toBeEmpty();
});

// -------------------------------------------------------------------------
// history
// -------------------------------------------------------------------------

it('the history endpoint returns a paginated list of calculations', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/commission/calculate', ['contract_id' => $this->contract->id]);

    $this->getJson('/api/v1/commission/history')
        ->assertOk()
        ->assertJsonStructure(['data', 'meta', 'links']);
});

it('the show endpoint returns a single calculation with input_values and calculation_steps populated', function () {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/v1/commission/calculate', ['contract_id' => $this->contract->id]);

    $id = CommissionCalculation::latest()->first()->id;

    $response = $this->getJson("/api/v1/commission/history/{$id}")->assertOk();

    expect($response->json('data.input_values'))->toBeArray()->not->toBeEmpty()
        ->and($response->json('data.calculation_steps'))->toBeArray()->not->toBeEmpty();
});

// -------------------------------------------------------------------------
// unauthenticated
// -------------------------------------------------------------------------

it('an unauthenticated request to any commission endpoint returns 401', function () {
    $this->getJson('/api/v1/commission/history')->assertUnauthorized();
    $this->postJson('/api/v1/commission/calculate', ['contract_id' => 1])->assertUnauthorized();
});
