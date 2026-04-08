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

        if (config('database.connections.central.driver') === 'sqlite') {
            $path = (string) config('database.connections.central.database');

            if ($path !== ':memory:' && is_file($path)) {
                @unlink($path);
            }
        }

        $this->artisan('migrate:fresh', array_merge($this->migrateFreshUsing(), [
            '--path' => database_path('migrations/central'),
            '--realpath' => true,
        ]));
        $this->app[Kernel::class]->setArtisan(null);
    }
}
