<?php

use App\Events\InvoicePaid;
use App\Listeners\SendReceipt;
use App\Mail\ReceiptMail;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;

/** Post a webhook body signed the way the provider signs it. */
function postWebhook(array $payload, ?string $secret = null): TestResponse
{
    $body = json_encode($payload);
    $signature = hash_hmac('sha256', $body, $secret ?? config('services.paygate.webhook_secret'));

    return test()->call('POST', '/webhooks/payments', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_PAYGATE_SIGNATURE' => $signature,
    ], $body);
}

function paymentEvent(Invoice $invoice, array $overrides = []): array
{
    return [
        'id' => 'evt_'.fake()->regexify('[a-z0-9]{12}'),
        'type' => 'payment.succeeded',
        'data' => array_replace([
            'reference' => 'pay_'.fake()->regexify('[a-z0-9]{12}'),
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'paid_at' => '2026-10-20T10:15:00Z',
        ], $overrides),
    ];
}

beforeEach(function () {
    $this->invoice = Invoice::factory()
        ->for(Company::factory())
        ->for(Client::factory()->state(['email' => 'billing@northwind.test']))
        ->sent()
        ->create(['number' => 'INV-2026-0007', 'currency' => 'EUR', 'total' => 1200_00]);
});

it('rejects a body with a bad signature and records nothing', function () {
    postWebhook(paymentEvent($this->invoice), secret: 'wrong-secret')->assertForbidden();

    $this->post('/webhooks/payments', paymentEvent($this->invoice))->assertForbidden();

    $this->assertDatabaseCount('payments', 0);
});

it('records a full payment, marks the invoice paid and emails a receipt', function () {
    Mail::fake();

    postWebhook(paymentEvent($this->invoice, ['reference' => 'pay_abc']))
        ->assertOk()
        ->assertJson(['status' => 'paid']);

    $this->assertDatabaseHas('payments', ['invoice_id' => $this->invoice->id, 'reference' => 'pay_abc', 'amount' => 1200_00]);
    expect($this->invoice->refresh()->status->value)->toBe('paid');

    // The listener is queued, but the test queue is sync, so the mail was sent in-request.
    Mail::assertSent(ReceiptMail::class, fn (ReceiptMail $mail) => $mail->hasTo('billing@northwind.test')
        && $mail->invoice->is($this->invoice));
});

it('keeps the invoice open after a partial payment', function () {
    Mail::fake();

    postWebhook(paymentEvent($this->invoice, ['amount' => 500_00]))->assertOk()->assertJson(['status' => 'sent']);

    expect($this->invoice->refresh())->status->value->toBe('sent')->amountPaid()->toBe(500_00);
    Mail::assertNothingSent();
});

it('marks paid once partial payments add up', function () {
    Mail::fake();

    postWebhook(paymentEvent($this->invoice, ['reference' => 'pay_1', 'amount' => 700_00]));
    postWebhook(paymentEvent($this->invoice, ['reference' => 'pay_2', 'amount' => 500_00]));

    expect($this->invoice->refresh()->status->value)->toBe('paid');
    Mail::assertSent(ReceiptMail::class, 1);
});

it('ignores a redelivered event instead of counting the money twice', function () {
    Mail::fake();
    $event = paymentEvent($this->invoice, ['reference' => 'pay_once']);

    postWebhook($event)->assertOk();
    postWebhook($event)->assertOk();

    $this->assertDatabaseCount('payments', 1);
    Mail::assertSent(ReceiptMail::class, 1);
});

it('refuses a payment in another currency', function () {
    postWebhook(paymentEvent($this->invoice, ['currency' => 'USD']))
        ->assertStatus(422)
        ->assertJsonPath('error', 'Payment in USD against an invoice in EUR.');

    $this->assertDatabaseCount('payments', 0);
});

it('answers 404 for an invoice it does not know', function () {
    postWebhook(paymentEvent($this->invoice, ['invoice_id' => 999_999]))->assertNotFound();
});

it('acknowledges event types it does not handle', function () {
    postWebhook([...paymentEvent($this->invoice), 'type' => 'payment.refunded'])
        ->assertOk()
        ->assertJson(['ignored' => 'payment.refunded']);

    $this->assertDatabaseCount('payments', 0);
});

it('dispatches InvoicePaid with the invoice', function () {
    // Faking one event switches off its listeners, so there is no receipt here. Say which
    // events you fake; a bare Event::fake() would also silence model events such as creating.
    Event::fake([InvoicePaid::class]);

    postWebhook(paymentEvent($this->invoice));

    Event::assertDispatched(InvoicePaid::class, fn (InvoicePaid $e) => $e->invoice->is($this->invoice));
    Event::assertListening(InvoicePaid::class, SendReceipt::class);
});
