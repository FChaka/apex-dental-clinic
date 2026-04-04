<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

/**
 * Feature tests that provision MySQL tenant databases: full central migrate:fresh each test (no RefreshDatabase static cache).
 */
abstract class TenancyFeatureTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', $this->migrateFreshUsing());
        $this->app[Kernel::class]->setArtisan(null);
    }
}
