<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

class InvoiceSent
{
    use Dispatchable;

    public function __construct(public Invoice $invoice) {}
}
