<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_reaches_the_spa_shell(): void
    {
        $user = User::factory()->create(['role' => 'viewer']);

        $response = $this->actingAs($user)->get('/');

        $response->assertStatus(200);
    }
}
