<?php

namespace App\Notifications;

use App\Billing\Money;
use App\Models\Invoice;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OverdueReminder extends Notification
{
    public function __construct(public Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = (int) $this->invoice->due_at->diffInDays(today());

        return (new MailMessage)
            ->subject("Reminder: invoice {$this->invoice->number} is {$days} days overdue")
            ->line("Invoice {$this->invoice->number} for ".Money::format($this->invoice->total, $this->invoice->currency)." was due on {$this->invoice->due_at->toFormattedDateString()}.")
            ->line('If you have already paid, please ignore this message.');
    }
}
