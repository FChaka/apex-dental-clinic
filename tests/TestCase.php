<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\Traits\CanConfigureMigrationCommands;

abstract class TestCase extends BaseTestCase
{
    use CanConfigureMigrationCommands;

    /**
     * Central migrations live under database/migrations/central and are not picked up by the default migrator path.
     *
     * @return array<string, mixed>
     */
    protected function migrateFreshUsing()
    {
        return array_merge(
            [
                '--drop-views' => $this->shouldDropViews(),
                '--drop-types' => $this->shouldDropTypes(),
            ],
            $this->seeder() ? ['--seeder' => $this->seeder()] : ['--seed' => $this->shouldSeed()],
            [
                '--path' => [
                    database_path('migrations'),
                    database_path('migrations/central'),
                ],
                '--realpath' => true,
            ]
        );
    }
}
