<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('currency', 3)->default('EUR');
            $table->decimal('default_tax_rate', 5, 2)->default(0);
            $table->unsignedSmallInteger('payment_terms_days')->default(14);
            $table->string('invoice_prefix', 10)->default('INV');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
