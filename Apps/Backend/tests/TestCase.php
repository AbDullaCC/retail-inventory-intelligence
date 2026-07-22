<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * A cached config (`php artisan config:cache`) silently overrides
     * phpunit.xml's environment, pointing tests at the REAL database — where
     * RefreshDatabase would wipe every table. Refuse to run in that state.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(sprintf(
                'Tests must run on sqlite :memory: but the app resolved "%s" ("%s"). '
                .'The config cache is probably stale — run `php artisan config:clear` and retry.',
                $connection,
                $database,
            ));
        }
    }
}
