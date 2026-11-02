<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The provider signs the raw request body with HMAC-SHA256 and the shared secret, and sends
 * the hex digest in a header. Verify against the raw body, never a re-encoded one, and compare
 * in constant time.
 */
class VerifyPaymentWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('services.paygate.webhook_secret');
        $signature = (string) $request->header('X-Paygate-Signature', '');

        if ($secret === '' || $signature === '') {
            abort(403, 'Missing webhook signature.');
        }

        if (! hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature)) {
            abort(403, 'Invalid webhook signature.');
        }

        return $next($request);
    }
}
