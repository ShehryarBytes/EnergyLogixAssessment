<?php

use App\Models\Formula;
use App\Models\User;
use App\Services\Formula\FormulaParserService;
use Laravel\Sanctum\Sanctum;

// Helper: build a formula with a real AST so it can be activated
function formulaWithAst(array $overrides = []): array
{
    $expression = $overrides['expression'] ?? 'AnnualUsage * 0.05';
    $parser     = new FormulaParserService();

    return array_merge([
        'name'       => 'Test Formula',
        'version'    => 1,
        'expression' => $expression,
        'ast_json'   => $parser->parse($expression),
        'status'     => 'draft',
        'created_by' => 'test@energylogix.com',
    ], $overrides);
}

beforeEach(function () {
    $this->admin  = User::factory()->create(['role' => 'admin']);
    $this->viewer = User::factory()->create(['role' => 'viewer']);
});

// -------------------------------------------------------------------------
// store
// -------------------------------------------------------------------------

it('an authenticated admin can create a formula and receives 201', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/v1/formulas', [
        'name'       => 'Standard Commission',
        'expression' => 'AnnualUsage * 0.05',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'Standard Commission')
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.version', 1);

    $this->assertDatabaseHas('formulas', ['name' => 'Standard Commission', 'version' => 1]);
});

it('an authenticated viewer cannot create a formula and receives 403', function () {
    Sanctum::actingAs($this->viewer);

    $response = $this->postJson('/api/v1/formulas', [
        'name'       => 'Should Not Save',
        'expression' => 'AnnualUsage * 0.05',
    ]);

    $response->assertStatus(403);
    $this->assertDatabaseMissing('formulas', ['name' => 'Should Not Save']);
});

it('creating a formula with an invalid expression returns 422 with an error message', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/v1/formulas', [
        'name'       => 'Bad Formula',
        'expression' => 'UnknownVar * 0.05',
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['message']);

    expect($response->json('message'))->toContain('UnknownVar');
});

it('a second formula with the same name gets version 2', function () {
    Sanctum::actingAs($this->admin);

    Formula::create(formulaWithAst(['name' => 'Versioned Formula', 'version' => 1]));

    $response = $this->postJson('/api/v1/formulas', [
        'name'       => 'Versioned Formula',
        'expression' => 'AnnualUsage * 0.05',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.version', 2);
});

it('a calculated variable with a reserved system variable name returns 422', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/v1/formulas', [
        'name'       => 'Reserved Name Test',
        'expression' => 'AnnualUsage * 0.05',
        'variables'  => [
            ['name' => 'AnnualUsage', 'expression' => 'ContractValue * 2'],
        ],
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['message']);

    expect($response->json('message'))->toContain('reserved');
});

// -------------------------------------------------------------------------
// activate
// -------------------------------------------------------------------------

it('activating a draft formula sets its status to active', function () {
    Sanctum::actingAs($this->admin);

    $formula = Formula::create(formulaWithAst());

    $this->postJson("/api/v1/formulas/{$formula->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    $this->assertDatabaseHas('formulas', ['id' => $formula->id, 'status' => 'active']);
});

it('activating a formula archives the previously active one', function () {
    Sanctum::actingAs($this->admin);

    $first  = Formula::create(formulaWithAst(['name' => 'First',  'version' => 1]));
    $second = Formula::create(formulaWithAst(['name' => 'Second', 'version' => 1]));

    $this->postJson("/api/v1/formulas/{$first->id}/activate")->assertOk();
    $this->postJson("/api/v1/formulas/{$second->id}/activate")->assertOk();

    $this->assertDatabaseHas('formulas', ['id' => $first->id,  'status' => 'archived']);
    $this->assertDatabaseHas('formulas', ['id' => $second->id, 'status' => 'active']);
});

it('activating an already-active formula returns 422', function () {
    Sanctum::actingAs($this->admin);

    $formula = Formula::create(formulaWithAst(['status' => 'active']));

    $this->postJson("/api/v1/formulas/{$formula->id}/activate")
        ->assertStatus(422);
});

// -------------------------------------------------------------------------
// validate
// -------------------------------------------------------------------------

it('the validate endpoint returns valid=true for a correct expression without saving', function () {
    Sanctum::actingAs($this->admin);
    $before = Formula::count();

    $response = $this->postJson('/api/v1/formulas/validate', [
        'expression' => 'AnnualUsage * 0.05',
    ]);

    $response->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonStructure(['ast']);

    expect(Formula::count())->toBe($before);
});

it('the validate endpoint returns 422 for an unknown variable without saving', function () {
    Sanctum::actingAs($this->admin);
    $before = Formula::count();

    $response = $this->postJson('/api/v1/formulas/validate', [
        'expression' => 'GhostVar * 100',
    ]);

    $response->assertStatus(422)
        ->assertJsonStructure(['message']);

    expect($response->json('message'))->toContain('GhostVar')
        ->and(Formula::count())->toBe($before);
});

it('a viewer cannot call the validate endpoint and receives 403', function () {
    Sanctum::actingAs($this->viewer);

    $this->postJson('/api/v1/formulas/validate', ['expression' => 'AnnualUsage * 0.05'])
        ->assertStatus(403);
});
