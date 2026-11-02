<?php

use App\Enums\InvoiceStatus;

it('allows the transitions of a real invoice lifecycle', function (InvoiceStatus $from, InvoiceStatus $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with([
    'draft to sent' => [InvoiceStatus::Draft, InvoiceStatus::Sent],
    'sent to paid' => [InvoiceStatus::Sent, InvoiceStatus::Paid],
    'sent to void' => [InvoiceStatus::Sent, InvoiceStatus::Void],
]);

it('refuses transitions that would rewrite history', function (InvoiceStatus $from, InvoiceStatus $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with([
    'paid to anything' => [InvoiceStatus::Paid, InvoiceStatus::Sent],
    'void to anything' => [InvoiceStatus::Void, InvoiceStatus::Draft],
    'draft straight to paid' => [InvoiceStatus::Draft, InvoiceStatus::Paid],
    'sent back to draft' => [InvoiceStatus::Sent, InvoiceStatus::Draft],
    'draft to void, drafts are deleted instead' => [InvoiceStatus::Draft, InvoiceStatus::Void],
]);

it('is final once paid or void', function () {
    expect(InvoiceStatus::Paid->transitions())->toBe([])
        ->and(InvoiceStatus::Void->transitions())->toBe([]);
});
