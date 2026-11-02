<?php

namespace App\Billing;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Daily rates from an HTTP API, cached for a day. One network call per pair per day. */
class ExchangeRates
{
    public function rate(string $from, string $to): float
    {
        if ($from === $to) {
            return 1.0;
        }

        return Cache::remember("rates:{$from}:{$to}", now()->addDay(), function () use ($from, $to) {
            try {
                $rate = Http::timeout(5)
                    ->retry(2, 100)
                    ->get(config('services.rates.url'), ['base' => $from, 'symbols' => $to])
                    ->throw()
                    ->json("rates.{$to}");
            } catch (RequestException|ConnectionException $e) {
                throw new RatesUnavailable("Could not fetch the {$from}/{$to} rate.", previous: $e);
            }

            if (! is_numeric($rate) || $rate <= 0) {
                throw new RatesUnavailable("No {$from}/{$to} rate in the response.");
            }

            return (float) $rate;
        });
    }

    public function convert(int $minor, string $from, string $to): int
    {
        $major = Money::toMajor($minor, $from) * $this->rate($from, $to);

        return (int) round($major * (10 ** Money::decimals($to)), 0, PHP_ROUND_HALF_UP);
    }
}
