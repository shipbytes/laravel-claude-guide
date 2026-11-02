<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Blade's @vite needs a built manifest; tests do not. CI's PHP job has no Node.
        $this->withoutVite();
    }
}
