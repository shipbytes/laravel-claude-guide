<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('month');                                  // first day of the month
            $table->string('status', 20)->default('pending');       // pending, ready, failed
            $table->string('batch_id', 80)->nullable();             // set when written through the Batch API
            $table->text('summary')->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_summaries');
    }
};
