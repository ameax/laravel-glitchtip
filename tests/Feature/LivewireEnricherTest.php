<?php

use Ameax\Glitchtip\Enrichers\LivewireEnricher;
use Ameax\Glitchtip\Tests\Fixtures\TestComponent;
use Livewire\Livewire;
use Sentry\Event;

beforeEach(function () {
    Livewire::component('glitchtip-test-component', TestComponent::class);

    request()->merge(['components' => [[
        'snapshot' => json_encode([
            'data' => ['email' => 'max@example.com'],
            'memo' => ['id' => 'abc123', 'name' => 'glitchtip-test-component', 'path' => 'accounts', 'method' => 'GET'],
        ]),
        'updates' => ['email' => 'erika@example.com'],
        'calls' => [['method' => 'save', 'params' => ['secret'], 'metadata' => []]],
    ]]]);
});

it('ignores requests that are no livewire update requests', function () {
    $event = Event::createEvent();

    (new LivewireEnricher)->enrich($event, includeData: true);

    expect($event->getContexts())->not->toHaveKey('livewire')
        ->and($event->getTags())->toBe([]);
});

it('adds the components with their data', function () {
    request()->headers->set('X-Livewire', '1');
    $event = Event::createEvent();

    (new LivewireEnricher)->enrich($event, includeData: true);

    expect($event->getTags())->toBe(['livewire.component' => TestComponent::class])
        ->and($event->getContexts()['livewire'])->toMatchArray([
            'original_url' => url('accounts'),
            'original_method' => 'GET',
            'components' => [[
                'class' => TestComponent::class,
                'name' => 'glitchtip-test-component',
                'id' => 'abc123',
                'calls' => [['method' => 'save', 'params' => ['secret'], 'metadata' => []]],
                'data' => ['email' => 'max@example.com'],
                'updates' => ['email' => 'erika@example.com'],
            ]],
        ]);
});

it('adds only the component and the called methods without data', function () {
    request()->headers->set('X-Livewire', '1');
    $event = Event::createEvent();

    (new LivewireEnricher)->enrich($event, includeData: false);

    expect($event->getContexts()['livewire']['components'])->toBe([[
        'class' => TestComponent::class,
        'name' => 'glitchtip-test-component',
        'id' => 'abc123',
        'calls' => ['save'],
    ]]);
});
