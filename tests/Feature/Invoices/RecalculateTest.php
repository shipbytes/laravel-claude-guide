<?php

use App\Models\Invoice;
use App\Models\InvoiceLine;

// Model logic that reads relations is a feature test by Laravel's definition: it needs the
// database. The arithmetic itself is covered by the unit tests of TotalsCalculator.

it('stores the totals computed from its lines', function () {
    $invoice = Invoice::factory()->create(['discount_percent' => 5]);
    InvoiceLine::factory()->for($invoice)->create(['quantity' => 2, 'unit_price' => 100_00, 'tax_rate' => 20]);
    InvoiceLine::factory()->for($invoice)->create(['quantity' => 1, 'unit_price' => 50_00, 'tax_rate' => 0]);

    $totals = $invoice->recalculate();

    expect($totals->subtotal)->toBe(250_00)
        ->and($totals->discount)->toBe(12_50)
        ->and($totals->tax)->toBe(38_00)   // 20 % of 190.00
        ->and($totals->total)->toBe(275_50);

    $this->assertDatabaseHas('invoices', ['id' => $invoice->id, 'total' => 275_50]);
});
