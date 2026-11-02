<?php

use App\Models\User;

it('shows the login page to guests', function () {
    $this->get('/login')
        ->assertOk()
        ->assertSee('Log in');
});

it('logs a user in with valid credentials and sends them to their invoices', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $this->post('/login', ['email' => $user->email, 'password' => 'secret-password'])
        ->assertRedirect('/invoices');

    $this->assertAuthenticatedAs($user);
});

it('rejects a wrong password without revealing which field was wrong', function () {
    $user = User::factory()->create(['password' => 'secret-password']);

    $this->from('/login')
        ->post('/login', ['email' => $user->email, 'password' => 'nope'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    $this->assertGuest();
});

it('validates the form before touching the guard', function (array $input, string $field) {
    $this->post('/login', $input)->assertSessionHasErrors($field);
})->with([
    'missing email' => [['password' => 'x'], 'email'],
    'malformed email' => [['email' => 'not-an-email', 'password' => 'x'], 'email'],
    'missing password' => [['email' => 'a@b.test'], 'password'],
]);

it('logs out and invalidates the session', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/logout')->assertRedirect('/login');

    $this->assertGuest();
});

it('sends logged-in users away from the login page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/login')
        ->assertRedirect('/');
});
