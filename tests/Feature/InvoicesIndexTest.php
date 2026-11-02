<?php

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;

it('requires a login', function () {
    $this->get('/invoices')->assertRedirect('/login');
});

it('lists only the invoices of the user\'s own company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->for($company)->create();
    $mine = Invoice::factory()->for($company)->sent()->create(['number' => 'INV-0001']);
    $theirs = Invoice::factory()->sent()->create(['number' => 'INV-9999']);

    $this->actingAs($user)
        ->get('/invoices')
        ->assertOk()
        ->assertSee($mine->number)
        ->assertDontSee($theirs->number);
});

it('shows an empty state when there is nothing to bill yet', function () {
    $this->actingAs(User::factory()->create())
        ->get('/invoices')
        ->assertOk()
        ->assertSee('No invoices yet.');
});
