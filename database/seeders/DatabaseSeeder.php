<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** A demo company to log in to: demo@invoicemini.test / password */
    public function run(): void
    {
        $company = Company::factory()->create(['name' => 'Acme Studio', 'currency' => 'EUR']);

        User::factory()->for($company)->create([
            'name' => 'Demo User',
            'email' => 'demo@invoicemini.test',
        ]);

        $clients = Client::factory()->count(3)->for($company)->create();

        foreach (['draft', 'sent', 'paid', 'overdue'] as $i => $state) {
            /** @var Invoice $invoice */
            Invoice::factory()->for($company)->for($clients[$i % 3])
                ->{$state}()
                ->has(InvoiceLine::factory()->count(2), 'lines')
                ->create($state === 'draft' ? [] : ['number' => 'INV-2026-000'.($i + 1)]);
        }
    }
}
