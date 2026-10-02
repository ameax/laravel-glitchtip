<?php

namespace Ameax\Glitchtip;

use Ameax\Glitchtip\Enrichers\EventEnricher;
use Ameax\Glitchtip\Filters\SensitiveDataFilter;
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
        $this->app->singleton(SensitiveDataFilter::class, fn ($app): SensitiveDataFilter => new SensitiveDataFilter(
            array_values(array_filter((array) $app['config']->get('glitchtip.sensitive_keys', []), 'is_string'))
        ));
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
