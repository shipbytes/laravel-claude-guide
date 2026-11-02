<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per request to the API: which feature, which model, the four token counts
        // the API bills separately, and the cost in dollars. The source of truth for "what
        // does this feature cost us", per company and per month.
        Schema::create('claude_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('feature', 40);
            $table->string('model', 60);
            $table->unsignedInteger('input_tokens');
            $table->unsignedInteger('cache_read_input_tokens')->default(0);
            $table->unsignedInteger('cache_creation_input_tokens')->default(0);
            $table->unsignedInteger('output_tokens');
            $table->decimal('cost', 10, 6);
            $table->string('stop_reason', 40)->nullable();
            $table->boolean('batch')->default(false);
            $table->timestamp('created_at');
            $table->index(['company_id', 'created_at']);
            $table->index(['feature', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claude_calls');
    }
};
