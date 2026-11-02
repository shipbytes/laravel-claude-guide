<?php

namespace App\Models;

use App\Billing\LineItem;
use App\Billing\Totals;
use App\Billing\TotalsCalculator;
use App\Enums\InvoiceStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['company_id', 'client_id', 'number', 'status', 'currency', 'issued_at', 'due_at', 'discount_percent', 'notes', 'subtotal', 'discount', 'tax', 'total'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issued_at' => 'date',
            'due_at' => 'date',
            'reminded_at' => 'datetime',
            'discount_percent' => 'decimal:2',
            'subtotal' => 'integer',
            'discount' => 'integer',
            'tax' => 'integer',
            'total' => 'integer',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function amountPaid(): int
    {
        return (int) $this->payments()->sum('amount');
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    /** Recompute the stored totals from the lines. Called after any change to them. */
    public function recalculate(): Totals
    {
        $lines = $this->lines()->get()->map(fn (InvoiceLine $line) => new LineItem(
            quantity: (float) $line->quantity,
            unitPrice: $line->unit_price,
            taxRate: (float) $line->tax_rate,
        ))->all();

        $totals = (new TotalsCalculator)->calculate($lines, (float) $this->discount_percent);
        $this->forceFill($totals->toArray())->save();

        return $totals;
    }

    /** The highest number issued by the company in a year, or null. Longer strings sort after shorter ones, so 10000 beats 9999. */
    public static function lastNumber(Company $company, string $prefix, int $year): ?string
    {
        return static::query()
            ->where('company_id', $company->id)
            ->where('number', 'like', strtoupper($prefix).'-'.$year.'-%')
            ->orderByRaw('length(number) desc, number desc')
            ->lockForUpdate()
            ->value('number');
    }
}
