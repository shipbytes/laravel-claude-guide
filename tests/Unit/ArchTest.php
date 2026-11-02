<?php

// Architecture tests run against the source, not the application: they belong with the unit tests.

arch()->preset()->php();
arch()->preset()->security();

arch('the billing domain stays pure, so it can be unit tested')
    ->expect('App\Billing')
    ->not->toUse(['Illuminate', 'App\Models'])
    ->ignoring(['App\Billing\Actions', 'App\Billing\ExchangeRates']);

arch('actions are the only place billing meets Eloquent')
    ->expect('App\Models')
    ->not->toBeUsedIn('App\Billing')
    ->ignoring('App\Billing\Actions');

arch('controllers')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller')
    ->toExtend('App\Http\Controllers\Controller');

arch('jobs and queued listeners are serialisable work')
    ->expect('App\Jobs')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');
