<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['company_id', 'feature', 'model', 'input_tokens', 'cache_read_input_tokens', 'cache_creation_input_tokens', 'output_tokens', 'cost', 'stop_reason', 'batch', 'created_at'])]
class ClaudeCall extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['cost' => 'decimal:6', 'batch' => 'boolean', 'created_at' => 'datetime'];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
