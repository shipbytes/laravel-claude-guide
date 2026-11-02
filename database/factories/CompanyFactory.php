<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'currency' => 'EUR',
            'default_tax_rate' => 20,
            'payment_terms_days' => 14,
            'invoice_prefix' => 'INV',
        ];
    }
}
