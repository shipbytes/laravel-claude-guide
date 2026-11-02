<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Every test under tests/Feature and tests/Browser boots the application and starts from an
// empty, migrated database. Tests under tests/Unit get plain PHPUnit: no container, no database.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

// Browser tests drive the real page, so they need the built assets that feature tests skip.
pest()->beforeEach(fn () => $this->withVite())->in('Browser');
