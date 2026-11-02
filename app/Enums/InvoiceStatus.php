<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Void = 'void';

    /** The statuses an invoice may move to from this one. */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent],
            self::Sent => [self::Paid, self::Void],
            self::Paid, self::Void => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->transitions(), true);
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
