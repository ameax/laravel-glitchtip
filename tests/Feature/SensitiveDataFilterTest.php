<?php

use Ameax\Glitchtip\Enrichers\EventEnricher;
use Ameax\Glitchtip\Filters\SensitiveDataFilter;
use Sentry\Event;

it('filters credentials in the request', function (bool $privacyMode) {
    config(['glitchtip.privacy_mode' => $privacyMode]);

    $event = Event::createEvent()->setRequest([
        'url' => 'https://example.test/reset?email=max%40example.com&token=abc123&signature=xyz',
        'query_string' => 'email=max%40example.com&token=abc123&signature=xyz',
        'data' => [
            'name' => 'Max Mustermann',
            'password' => 'geheim',
            'password_confirmation' => 'geheim',
            '_token' => 'csrf',
            'nested' => ['api_key' => 'key', 'iban' => 'DE89370400440532013000'],
        ],
        'headers' => [
            'cookie' => ['laravel_session=eyJpdiI6'],
            'authorization' => ['Bearer abc'],
            'x-csrf-token' => ['csrf'],
            'user-agent' => ['curl/8.0'],
        ],
        'cookies' => ['laravel_session' => 'eyJpdiI6'],
    ]);

    $request = app(EventEnricher::class)->enrich($event)->getRequest();

    expect($request['url'])->toBe('https://example.test/reset?email=max%40example.com&token=%5BFiltered%5D&signature=%5BFiltered%5D')
        ->and($request['query_string'])->toBe('email=max%40example.com&token=%5BFiltered%5D&signature=%5BFiltered%5D')
        ->and($request['data'])->toBe([
            'name' => 'Max Mustermann',
            'password' => '[Filtered]',
            'password_confirmation' => '[Filtered]',
            '_token' => '[Filtered]',
            'nested' => ['api_key' => '[Filtered]', 'iban' => 'DE89370400440532013000'],
        ])
        ->and($request['headers'])->toBe([
            'cookie' => '[Filtered]',
            'authorization' => '[Filtered]',
            'x-csrf-token' => '[Filtered]',
            'user-agent' => ['curl/8.0'],
        ])
        ->and($request['cookies'])->toBe(['laravel_session' => '[Filtered]']);
})->with([
    'privacy mode enabled' => true,
    'privacy mode disabled' => false,
]);

it('filters credentials inside livewire snapshots and the livewire context', function () {
    $snapshot = json_encode(['data' => ['email' => 'max@example.com', 'password' => 'geheim'], 'memo' => ['name' => 'login']]);

    $event = Event::createEvent()
        ->setRequest(['data' => ['components' => [['snapshot' => $snapshot, 'updates' => ['password' => 'neu']]]]])
        ->setContext('livewire', ['components' => [['data' => ['password' => 'geheim', 'email' => 'max@example.com']]]]);

    app(SensitiveDataFilter::class)->filter($event);

    $component = $event->getRequest()['data']['components'][0];

    expect(json_decode($component['snapshot'], true)['data'])->toBe(['email' => 'max@example.com', 'password' => '[Filtered]'])
        ->and($component['updates'])->toBe(['password' => '[Filtered]'])
        ->and($event->getContexts()['livewire']['components'][0]['data'])->toBe(['password' => '[Filtered]', 'email' => 'max@example.com']);
});

it('uses the configured sensitive keys', function () {
    $filter = new SensitiveDataFilter(['iban']);

    expect($filter->filterArray(['iban' => 'DE89', 'password' => 'geheim']))->toBe(['iban' => '[Filtered]', 'password' => 'geheim']);
});
