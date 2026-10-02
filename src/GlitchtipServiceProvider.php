<?php

namespace Ameax\Glitchtip;

use Ameax\Glitchtip\Enrichers\EventEnricher;
use Illuminate\Contracts\Config\Repository;
use Sentry\Event;
use Sentry\State\Scope;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GlitchtipServiceProvider extends PackageServiceProvider
{
    private static bool $eventProcessorRegistered = false;

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-glitchtip')
            ->hasConfigFile('glitchtip');
    }

    public function packageRegistered(): void
    {
        Glitchtip::configureSentry($this->app->make(Repository::class), $this->app->basePath());

        $this->app->singleton(EventEnricher::class);
    }

    public function packageBooted(): void
    {
        if (self::$eventProcessorRegistered) {
            return;
        }

        self::$eventProcessorRegistered = true;

        Scope::addGlobalEventProcessor(
            static fn (Event $event): Event => app(EventEnricher::class)->enrich($event)
        );
    }
}
