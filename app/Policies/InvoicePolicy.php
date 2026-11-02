<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->company_id === $invoice->company_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    /** Only drafts change. A sent invoice is a document the client has; it is voided and reissued. */
    public function update(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $invoice->isDraft();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $this->update($user, $invoice);
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $invoice->status->canTransitionTo(InvoiceStatus::Void);
    }
}
