<?php

use App\Models\CommissionCalculation;
use App\Models\Contract;
use App\Models\Formula;
use App\Models\ImpactAnalysis;
use App\Models\User;
use App\Services\Formula\FormulaEvaluatorService;
use App\Services\Formula\FormulaParserService;
use App\Services\SimulationService;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

// Unique name — avoids conflict with formulaWithAst() in FormulaApiTest.php
function simDraftFormula(): Formula
{
    $parser = new FormulaParserService();

    return Formula::create([
        'name'       => 'Simulation Test Formula',
        'version'    => 1,
        'expression' => 'AnnualUsage * 0.05',
        'ast_json'   => $parser->parse('AnnualUsage * 0.05'),
        'status'     => 'draft',
        'created_by' => 'test@energylogix.com',
    ]);
}

beforeEach(function () {
    $this->admin   = User::factory()->create(['role' => 'admin']);
    $this->viewer  = User::factory()->create(['role' => 'viewer']);
    $this->formula = simDraftFormula();
});

// -------------------------------------------------------------------------
// run
// -------------------------------------------------------------------------

it('an authenticated admin can run a simulation and immediately gets 202 with pending status and an id', function () {
    Queue::fake(); // prevent synchronous processing so status stays pending in the response

    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/simulation/run', ['formula_id' => $this->formula->id])
        ->assertStatus(202)
        ->assertJsonStructure(['id', 'status'])
        ->assertJsonPath('status', 'pending');
});

it('the run endpoint creates a record in impact_analyses with status pending', function () {
    Queue::fake();

    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/simulation/run', ['formula_id' => $this->formula->id]);

    $this->assertDatabaseHas('impact_analyses', [
        'formula_id' => $this->formula->id,
        'status'     => 'pending',
    ]);
});

it('an authenticated viewer cannot run a simulation and gets 403', function () {
    Sanctum::actingAs($this->viewer);

    $this->postJson('/api/v1/simulation/run', ['formula_id' => $this->formula->id])
        ->assertStatus(403);
});

it('the show endpoint returns the current status of a simulation', function () {
    Queue::fake();

    Sanctum::actingAs($this->admin);

    $id = $this->postJson('/api/v1/simulation/run', ['formula_id' => $this->formula->id])->json('id');

    $this->getJson("/api/v1/simulation/{$id}")
        ->assertOk()
        ->assertJsonPath('data.status', 'pending');
});

it('running a simulation does not create any records in commission_calculations', function () {
    Sanctum::actingAs($this->admin);

    $before = CommissionCalculation::count();

    $this->postJson('/api/v1/simulation/run', ['formula_id' => $this->formula->id]);

    expect(CommissionCalculation::count())->toBe($before);
});

// -------------------------------------------------------------------------
// job processing
// -------------------------------------------------------------------------

it('the simulation job when processed directly updates the record to complete with all numeric fields', function () {
    // Create a contract so there is something to process
    Contract::create([
        'customer_name'   => 'Job Test Customer',
        'annual_usage'    => '200000.0000',
        'contract_value'  => '40000.0000',
        'contract_length' => 36,
        'risk_score'      => '2.00',
    ]);

    $analysis = ImpactAnalysis::create([
        'formula_id'   => $this->formula->id,
        'triggered_by' => 'test@energylogix.com',
        'status'       => 'pending',
    ]);

    (new SimulationService(new FormulaEvaluatorService()))->run($analysis->id);

    $analysis->refresh();

    expect($analysis->status->value)->toBe('complete')
        ->and($analysis->affected_contracts)->not->toBeNull()
        ->and($analysis->new_total)->not->toBeNull()
        ->and($analysis->current_total)->not->toBeNull()
        ->and($analysis->difference)->not->toBeNull();
});

it('a simulation run against a formula with no contracts completes without error and shows zero totals', function () {
    // RefreshDatabase → contracts table is empty; no contracts created in this test
    $analysis = ImpactAnalysis::create([
        'formula_id'   => $this->formula->id,
        'triggered_by' => 'test@energylogix.com',
        'status'       => 'pending',
    ]);

    (new SimulationService(new FormulaEvaluatorService()))->run($analysis->id);

    $analysis->refresh();

    expect($analysis->status->value)->toBe('complete')
        ->and($analysis->affected_contracts)->toBe(0)
        ->and((float) $analysis->new_total)->toBe(0.0)
        ->and((float) $analysis->current_total)->toBe(0.0);
});
