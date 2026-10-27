<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $sqlite = $app['config']->get('database.connections.sqlite');
        $databaseUrl = $sqlite['url'] ?? null;

        if (
            $app['config']->get('database.default') !== 'sqlite'
            || ($sqlite['database'] ?? null) !== ':memory:'
            || ($databaseUrl !== null && $databaseUrl !== '')
        ) {
            throw new \RuntimeException(
                'Tests require isolated in-memory SQLite. Set DB_CONNECTION=sqlite, DB_DATABASE=:memory:, and DB_URL=.',
            );
        }

        return $app;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
