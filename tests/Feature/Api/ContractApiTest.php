<?php

use App\Models\Contract;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->admin  = User::factory()->create(['role' => 'admin']);
    $this->viewer = User::factory()->create(['role' => 'viewer']);

    $this->contract = Contract::create([
        'customer_name'   => 'Existing Customer Ltd',
        'annual_usage'    => '150000.0000',
        'contract_value'  => '28000.0000',
        'contract_length' => 36,
        'risk_score'      => '4.00',
    ]);
});

it('an authenticated user can list all contracts', function () {
    Sanctum::actingAs($this->viewer);

    $this->getJson('/api/v1/contracts')
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonCount(1, 'data');
});

it('an authenticated admin can create a contract and gets a 201 response', function () {
    Sanctum::actingAs($this->admin);

    $response = $this->postJson('/api/v1/contracts', [
        'customer_name'   => 'New Customer plc',
        'annual_usage'    => 250000,
        'contract_value'  => 48000,
        'contract_length' => 48,
        'risk_score'      => 3.5,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.customer_name', 'New Customer plc');

    $this->assertDatabaseHas('contracts', ['customer_name' => 'New Customer plc']);
});

it('creating a contract with missing required fields returns 422', function () {
    Sanctum::actingAs($this->admin);

    $this->postJson('/api/v1/contracts', ['customer_name' => 'Incomplete'])
        ->assertStatus(422)
        ->assertJsonStructure(['errors']);
});

it('an authenticated admin can update a contract', function () {
    Sanctum::actingAs($this->admin);

    $this->putJson("/api/v1/contracts/{$this->contract->id}", [
        'customer_name' => 'Updated Customer Ltd',
    ])->assertOk()
      ->assertJsonPath('data.customer_name', 'Updated Customer Ltd');

    $this->assertDatabaseHas('contracts', ['id' => $this->contract->id, 'customer_name' => 'Updated Customer Ltd']);
});

it('an authenticated admin can delete a contract', function () {
    Sanctum::actingAs($this->admin);

    $this->deleteJson("/api/v1/contracts/{$this->contract->id}")
        ->assertStatus(204);

    $this->assertDatabaseMissing('contracts', ['id' => $this->contract->id]);
});
