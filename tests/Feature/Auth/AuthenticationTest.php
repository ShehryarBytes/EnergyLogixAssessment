<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create([
        'email'    => 'test@energylogix.com',
        'password' => bcrypt('password'),
        'role'     => 'viewer',
    ]);
});

it('a user with correct credentials can log in and is redirected to home', function () {
    $this->post('/login', [
        'email'    => 'test@energylogix.com',
        'password' => 'password',
    ])->assertRedirect('/');
});

it('a user with wrong credentials cannot log in and the session has an error', function () {
    $this->post('/login', [
        'email'    => 'test@energylogix.com',
        'password' => 'wrongpassword',
    ])->assertSessionHasErrors('email');
});

it('a logged-in user can sign out and is redirected to the login page', function () {
    $this->actingAs($this->user)
        ->post('/logout')
        ->assertRedirect('/login');
});

it('an unauthenticated user visiting a protected route is redirected to login', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/formulas')->assertRedirect('/login');
    $this->get('/audit')->assertRedirect('/login');
});

it('the registration route returns 404', function () {
    $this->get('/register')->assertNotFound();
});
