<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;

/**
 * Feature tests that provision tenant DBs (SQLite in testing, MySQL in local CI-style runs): central migrate:fresh each test.
 */
abstract class TenancyFeatureTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('database.connections.central.driver') === 'sqlite') {
            $path = (string) config('database.connections.central.database');

            if ($path !== ':memory:') {
                if (is_file($path)) {
                    @unlink($path);
                }
                touch($path);
            }
        }

        $this->artisan('migrate:fresh', array_merge($this->migrateFreshUsing(), [
            '--database' => 'central',
        ]));
        $this->app[Kernel::class]->setArtisan(null);
    }
}
