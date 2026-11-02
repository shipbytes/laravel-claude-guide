<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InvoiceLine> */
class InvoiceLineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->randomElement(['Design work', 'Development', 'Consulting', 'Hosting, monthly']),
            'quantity' => fake()->randomElement([1, 2, 4, 10]),
            'unit_price' => fake()->numberBetween(5_000, 150_000),
            'tax_rate' => 20,
            'position' => 0,
        ];
    }
}
