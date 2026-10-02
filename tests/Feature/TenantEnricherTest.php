<?php

use Ameax\Glitchtip\Enrichers\TenantEnricher;
use Ameax\Glitchtip\Tests\Fixtures\FakeTenant;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Jobs\SyncJob;
use Sentry\Event;

beforeEach(function () {
    config(['multitenancy.tenant_model' => FakeTenant::class]);
    FakeTenant::$current = null;
});

it('adds the current tenant', function () {
    FakeTenant::$current = new FakeTenant(['id' => 7, 'name' => 'aratest']);
    $event = Event::createEvent();

    app(TenantEnricher::class)->enrich($event);

    expect($event->getTags())->toBe(['tenant_id' => '7', 'tenant' => 'aratest']);
});

it('keeps the tenant of a failed job after multitenancy forgot it', function () {
    $enricher = app(TenantEnricher::class);

    event(new JobProcessing('sync', new SyncJob(app(), json_encode(['displayName' => 'TestJob', 'job' => 'TestJob', 'data' => []]), 'sync', 'default')));
    $enricher->tenantMadeCurrent(new FakeTenant(['id' => 7, 'name' => 'aratest']));

    $event = Event::createEvent();
    $enricher->enrich($event);

    expect($event->getTags())->toBe(['tenant_id' => '7', 'tenant' => 'aratest']);
});

it('forgets the job tenant before the worker continues with the next job', function () {
    $enricher = app(TenantEnricher::class);

    event(new JobProcessing('sync', new SyncJob(app(), json_encode(['displayName' => 'TestJob', 'job' => 'TestJob', 'data' => []]), 'sync', 'default')));
    $enricher->tenantMadeCurrent(new FakeTenant(['id' => 7, 'name' => 'aratest']));
    event(new Looping('redis', 'default'));

    $event = Event::createEvent();
    $enricher->enrich($event);

    expect($event->getTags())->toBe([]);
});

it('ignores remembered tenants outside of jobs', function () {
    $enricher = app(TenantEnricher::class);
    $enricher->tenantMadeCurrent(new FakeTenant(['id' => 7, 'name' => 'aratest']));

    $event = Event::createEvent();
    $enricher->enrich($event);

    expect($event->getTags())->toBe([]);
});
