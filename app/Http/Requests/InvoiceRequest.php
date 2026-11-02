<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    /** Runs before the rules: a forbidden request is answered 403, never "the lines field is required". */
    public function authorize(): bool
    {
        $invoice = $this->route('invoice');

        return $invoice instanceof Invoice
            ? $this->user()->can('update', $invoice)
            : $this->user()->can('create', Invoice::class);
    }

    public function rules(): array
    {
        return [
            // A client id from another company is "not found", not "forbidden": the rule hides it.
            'client_id' => ['required', Rule::exists('clients', 'id')->where('company_id', $this->user()->company_id)],
            'currency' => ['required', 'string', 'size:3', Rule::in(['EUR', 'USD', 'GBP', 'JPY'])],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'lines.*.unit_price' => ['required', 'integer', 'min:0'],
            'lines.*.tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'lines.*.description' => 'description',
            'lines.*.quantity' => 'quantity',
            'lines.*.unit_price' => 'unit price',
            'lines.*.tax_rate' => 'tax rate',
        ];
    }
}
