<?php

namespace Ameax\Glitchtip;

use Ameax\Glitchtip\Enrichers\EventEnricher;
use Ameax\Glitchtip\Enrichers\TenantEnricher;
use Ameax\Glitchtip\Filters\SensitiveDataFilter;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
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
        $this->app->singleton(TenantEnricher::class);
        $this->app->singleton(SensitiveDataFilter::class, fn ($app): SensitiveDataFilter => new SensitiveDataFilter(
            array_values(array_filter((array) $app['config']->get('glitchtip.sensitive_keys', []), 'is_string'))
        ));
    }

    public function packageBooted(): void
    {
        Event::listen(JobProcessing::class, fn () => $this->app->make(TenantEnricher::class)->jobStarted());
        Event::listen([JobProcessed::class, Looping::class], fn () => $this->app->make(TenantEnricher::class)->workerContinued());

        $madeTenantCurrentEvent = 'Spatie\\Multitenancy\\Events\\MadeTenantCurrentEvent';

        if (class_exists($madeTenantCurrentEvent)) {
            Event::listen($madeTenantCurrentEvent, function (object $event): void {
                if (isset($event->tenant) && is_object($event->tenant)) {
                    $this->app->make(TenantEnricher::class)->tenantMadeCurrent($event->tenant);
                }
            });
        }
    }
}
