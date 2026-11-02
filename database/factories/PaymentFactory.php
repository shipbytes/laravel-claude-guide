<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory()->sent(),
            'amount' => fake()->numberBetween(10_000, 200_000),
            'currency' => 'EUR',
            'provider' => 'paygate',
            'reference' => 'pay_'.fake()->unique()->regexify('[a-z0-9]{16}'),
            'paid_at' => now(),
        ];
    }
}
