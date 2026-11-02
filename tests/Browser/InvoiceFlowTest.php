<?php

use App\Jobs\DeliverInvoice;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

// Browser tests boot the same application as feature tests, in the same process, so factories,
// RefreshDatabase, actingAs() and the fakes all still apply. What changes is that a real
// Chromium renders the page and runs its JavaScript.

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Acme Studio', 'currency' => 'EUR', 'default_tax_rate' => 20]);
    $this->user = User::factory()->for($this->company)->create(['email' => 'demo@invoicemini.test', 'password' => 'password']);
    $this->client = Client::factory()->for($this->company)->create(['name' => 'Northwind Traders']);
});

it('logs in through the form', function () {
    visit('/login')
        ->assertSee('Sign in to InvoiceMini')
        ->type('email', 'demo@invoicemini.test')
        ->type('password', 'password')
        ->press('Log in')
        ->assertPathIs('/invoices')
        ->assertSee('No invoices yet.');

    $this->assertAuthenticatedAs($this->user);
});

it('creates an invoice with a line added by the page script, then sends it', function () {
    Bus::fake(); // sending queues the PDF and mail; not the subject here
    $this->actingAs($this->user);

    $page = visit('/invoices/create')
        ->select('client_id', (string) $this->client->id)
        ->type('input[name="lines[0][description]"]', 'Design work')
        ->type('input[name="lines[0][quantity]"]', '2')
        ->type('input[name="lines[0][unit_price]"]', '50000')
        ->click('#add-line')                        // app.js clones the row and re-indexes the names
        ->assertCount('tr.line', 2)
        ->type('input[name="lines[1][description]"]', 'Hosting')
        ->type('input[name="lines[1][unit_price]"]', '2000')
        ->screenshot(fullPage: false, filename: 'invoice-form')
        ->press('Create draft')
        ->assertSee('Invoice created.');

    $invoice = Invoice::sole();

    $page->assertPathIs("/invoices/{$invoice->id}")
        ->assertSeeIn('[data-subtotal]', '€1,020.00')
        ->assertSeeIn('[data-total]', '€1,224.00')
        ->screenshot(fullPage: false, filename: 'invoice-draft')
        ->press('Send')
        ->assertSee('Invoice INV-2026-0001 sent.')
        ->assertSeeIn('[data-status]', 'Sent')
        ->screenshot(fullPage: false, filename: 'invoice-sent');

    expect($invoice->refresh())->number->toBe('INV-2026-0001')->lines->toHaveCount(2);
    Bus::assertDispatched(DeliverInvoice::class);
});

it('removes a line in the browser and the server never sees it', function () {
    $this->actingAs($this->user);

    visit('/invoices/create')
        ->select('client_id', (string) $this->client->id)
        ->type('input[name="lines[0][description]"]', 'Keep me')
        ->type('input[name="lines[0][unit_price]"]', '1000')
        ->click('#add-line')
        ->type('input[name="lines[1][description]"]', 'Remove me')
        ->click('tr.line:nth-child(2) .remove-line')
        ->assertCount('tr.line', 1)
        ->press('Create draft')
        ->assertSee('Invoice created.')
        ->assertSee('Keep me')
        ->assertDontSee('Remove me');

    expect(Invoice::sole()->lines)->toHaveCount(1);
});

it('shows validation errors next to the form, not a blank page', function () {
    $this->actingAs($this->user);

    visit('/invoices/create')
        ->press('Create draft')
        ->assertPathIs('/invoices/create')
        ->assertSee('Please fix the following:')
        ->assertSee('The client id field is required.')
        ->assertSee('The description field is required.');
});

it('renders the main pages without script errors or serious accessibility issues', function () {
    $this->actingAs($this->user);
    $invoice = Invoice::factory()->recycle($this->company)->withLines(2)->sent()->create();

    visit(['/invoices', "/invoices/{$invoice->id}", '/invoices/create'])
        ->assertNoSmoke()
        ->assertNoAccessibilityIssues();
});
