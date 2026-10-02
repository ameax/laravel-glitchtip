<?php

declare(strict_types=1);

namespace Ameax\Glitchtip\Tests;

use Ameax\Glitchtip\Glitchtip;
use Ameax\Glitchtip\GlitchtipServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Sentry\Laravel\ServiceProvider as SentryServiceProvider;

class TestCase extends Orchestra
{
    protected function tearDown(): void
    {
        Glitchtip::flushEnrichers();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            SentryServiceProvider::class,
            LivewireServiceProvider::class,
            GlitchtipServiceProvider::class,
        ];
    }
}
