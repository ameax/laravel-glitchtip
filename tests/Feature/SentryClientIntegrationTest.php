<?php

use Ameax\Glitchtip\Glitchtip;
use Ameax\Glitchtip\Tests\Fixtures\FakeTenant;
use Ameax\Glitchtip\Tests\Fixtures\FakeTransport;
use Ameax\Glitchtip\Tests\Fixtures\FakeUser;
use GuzzleHttp\Psr7\ServerRequest;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\Laravel\Http\LaravelRequestFetcher;
use Sentry\SentrySdk;
use Sentry\State\Hub;

/**
 * Sends an exception through the Sentry client as configured by sentry-laravel
 * (including the request integration) and returns the event handed to the transport.
 */
function captureThroughSentryClient(): ?Event
{
    $transport = new FakeTransport;
    $client = app(ClientBuilder::class)->setTransport($transport)->getClient();

    $hub = new Hub($client);
    SentrySdk::setCurrentHub($hub);
    $hub->captureException(new RuntimeException('boom'));

    return $transport->events[0] ?? null;
}

beforeEach(function () {
    config(['sentry.dsn' => 'https://public@glitchtip.example/1', 'multitenancy.tenant_model' => FakeTenant::class]);
    FakeTenant::$current = new FakeTenant(['id' => 7, 'name' => 'aratest']);

    $this->actingAs((new FakeUser)->forceFill(['id' => 42]));

    request()->server->set('REMOTE_ADDR', '203.0.113.42');
    app()->instance(LaravelRequestFetcher::CONTAINER_PSR7_INSTANCE_KEY, (new ServerRequest(
        'POST',
        'https://example.test/accounts?token=abc',
        ['Content-Length' => '40', 'Cookie' => 'laravel_session=secret-session', 'Authorization' => 'Bearer abc', 'User-Agent' => 'curl/8.0'],
        null,
        '1.1',
        ['REMOTE_ADDR' => '10.0.0.1'],
    ))->withParsedBody(['name' => 'Max Mustermann', 'password' => 'geheim']));
});

afterEach(function () {
    FakeTenant::$current = null;
});

it('replaces the before_send callback of the sentry config', function () {
    expect(config('sentry.before_send'))->toBe([Glitchtip::class, 'beforeSend']);
});

it('enriches and filters the complete event including the request data of the sdk', function () {
    config(['glitchtip.privacy_mode' => false]);

    $event = captureThroughSentryClient();
    $request = $event?->getRequest() ?? [];

    expect($event?->getTags())->toMatchArray(['tenant' => 'aratest', 'tenant_id' => '7', 'locale' => 'en'])
        ->and($event?->getUser()?->getId())->toBe(42)
        ->and($event?->getUser()?->getIpAddress())->toBe('203.0.113.42')
        ->and($request['data'])->toBe(['name' => 'Max Mustermann', 'password' => '[Filtered]'])
        ->and($request['headers']['Cookie'])->toBe('[Filtered]')
        ->and($request['headers']['Authorization'])->toBe('[Filtered]')
        ->and($request['url'])->toBe('https://example.test/accounts?token=%5BFiltered%5D');
});

it('sends no personal data in privacy mode', function () {
    config(['glitchtip.privacy_mode' => true]);
    Glitchtip::configureSentry(config(), base_path());

    $event = captureThroughSentryClient();
    $request = $event?->getRequest() ?? [];

    expect($event?->getUser()?->getId())->toBe(42)
        ->and($event?->getUser()?->getIpAddress())->toBeNull()
        ->and($request)->not->toHaveKey('data')
        ->and($request)->not->toHaveKey('cookies')
        ->and($event?->getTags())->toHaveKey('tenant', 'aratest');
});

it('calls the before_send callback of the project afterwards', function () {
    config(['glitchtip.project_before_send' => fn (Event $event, ?EventHint $hint): ?Event => null]);

    expect(captureThroughSentryClient())->toBeNull();
});
