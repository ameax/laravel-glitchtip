<?php

use Ameax\Glitchtip\Enrichers\EventEnricher;
use Ameax\Glitchtip\Glitchtip;
use Ameax\Glitchtip\Tests\Fixtures\FakeTenant;
use Ameax\Glitchtip\Tests\Fixtures\FakeUser;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\State\Hub;
use Sentry\UserDataBag;

beforeEach(function () {
    config(['multitenancy.tenant_model' => FakeTenant::class]);
    FakeTenant::$current = new FakeTenant(['id' => 7, 'name' => 'aratest']);

    $this->actingAs((new FakeUser)->forceFill(['id' => 42]));

    $session = new Store('test', new ArraySessionHandler(1));
    $session->start();
    request()->setLaravelSession($session);
    request()->server->set('REMOTE_ADDR', '203.0.113.42');

    $this->enrich = fn (Event $event): Event => app(EventEnricher::class)->enrich($event);
    $this->httpEvent = fn (): Event => Event::createEvent()->setRequest(['url' => 'https://example.test/accounts']);
});

afterEach(function () {
    FakeTenant::$current = null;
});

it('always adds the user id, the tenant and the locale', function (bool $privacyMode) {
    config(['glitchtip.privacy_mode' => $privacyMode]);

    $event = ($this->enrich)(Event::createEvent());

    expect($event->getUser()?->getId())->toBe(42)
        ->and($event->getTags())->toBe(['locale' => 'en', 'tenant_id' => '7', 'tenant' => 'aratest']);
})->with([
    'privacy mode enabled' => true,
    'privacy mode disabled' => false,
]);

it('keeps a user id already set by the sdk', function () {
    $event = Event::createEvent()->setUser(UserDataBag::createFromUserIdentifier(99));

    expect(($this->enrich)($event)->getUser()?->getId())->toBe(99);
});

it('skips the tenant without multitenancy', function () {
    config(['multitenancy.tenant_model' => null]);

    expect(($this->enrich)(Event::createEvent())->getTags())->not->toHaveKey('tenant');
});

it('adds the full ip address and the session id of http requests without privacy mode', function () {
    config(['glitchtip.privacy_mode' => false]);

    $event = ($this->enrich)(($this->httpEvent)());

    expect($event->getUser()?->getIpAddress())->toBe('203.0.113.42')
        ->and($event->getContexts())->toHaveKey('session', ['id' => request()->session()->getId()]);
});

it('adds no ip address to events of console commands and jobs', function () {
    config(['glitchtip.privacy_mode' => false]);

    expect(($this->enrich)(Event::createEvent())->getUser()?->getIpAddress())->toBeNull();
});

it('adds neither ip address nor session id in privacy mode', function () {
    config(['glitchtip.privacy_mode' => true]);

    $event = ($this->enrich)(($this->httpEvent)());

    expect($event->getUser()?->getIpAddress())->toBeNull()
        ->and($event->getContexts())->not->toHaveKey('session');
});

it('runs project specific enrichers', function () {
    Glitchtip::enrichUsing(fn (Event $event) => $event->setTag('installation', 'kocher'));
    Glitchtip::enrichUsing(fn () => throw new RuntimeException('must not break the event'));

    expect(($this->enrich)(Event::createEvent())->getTags())->toHaveKey('installation', 'kocher');
});

it('is registered as global sentry event processor', function () {
    config(['glitchtip.privacy_mode' => true]);
    $captured = null;

    $client = ClientBuilder::create([
        'dsn' => 'https://public@glitchtip.example/1',
        'before_send' => function (Event $event) use (&$captured): ?Event {
            $captured = $event;

            return null;
        },
    ])->getClient();

    (new Hub($client))->captureMessage('test');

    expect($captured?->getTags())->toHaveKey('tenant', 'aratest')
        ->and($captured?->getUser()?->getId())->toBe(42);
});
