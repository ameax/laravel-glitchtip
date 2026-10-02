<?php

namespace Ameax\Glitchtip;

use Ameax\Glitchtip\Enrichers\EventEnricher;
use Ameax\Glitchtip\Filters\SensitiveDataFilter;
use Illuminate\Contracts\Config\Repository;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class GlitchtipServiceProvider extends PackageServiceProvider
{
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
}
