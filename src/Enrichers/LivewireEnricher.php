<?php

namespace Ameax\Glitchtip\Enrichers;

use Livewire\LivewireManager;
use Sentry\Event;
use Throwable;

/**
 * Adds the involved components to events raised during Livewire (v3/v4) update requests.
 *
 * Component class, name, id and the called methods are always attached. The component
 * data, the updates and the call parameters are only attached when personal data may be sent.
 */
class LivewireEnricher
{
    public function enrich(Event $event, bool $includeData): void
    {
        if (! class_exists(LivewireManager::class)) {
            return;
        }

        try {
            $livewire = app(LivewireManager::class);

            if (! $livewire->isLivewireRequest()) {
                return;
            }

            $components = $this->components($includeData);

            if ($components === []) {
                return;
            }

            $event->setTag('livewire.component', (string) ($components[0]['class'] ?? $components[0]['name'] ?? 'unknown'));
            $event->setContext('livewire', [
                'original_url' => $livewire->originalUrl(),
                'original_method' => $livewire->originalMethod(),
                'components' => $components,
            ]);
        } catch (Throwable) {
            return;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function components(bool $includeData): array
    {
        $components = [];

        foreach ((array) request()->input('components', []) as $component) {
            if (! is_array($component) || ! is_string($component['snapshot'] ?? null)) {
                continue;
            }

            $snapshot = json_decode($component['snapshot'], true);
            $snapshot = is_array($snapshot) ? $snapshot : [];
            $name = data_get($snapshot, 'memo.name');
            $calls = is_array($component['calls'] ?? null) ? $component['calls'] : [];

            $data = [
                'class' => is_string($name) ? $this->resolveClass($name) : null,
                'name' => $name,
                'id' => data_get($snapshot, 'memo.id'),
                'calls' => array_map(fn (mixed $call): mixed => is_array($call) ? ($call['method'] ?? null) : null, $calls),
            ];

            if ($includeData) {
                $data['data'] = $snapshot['data'] ?? [];
                $data['updates'] = $component['updates'] ?? [];
                $data['calls'] = $calls;
            }

            $components[] = $data;
        }

        return $components;
    }

    private function resolveClass(string $name): ?string
    {
        try {
            // Livewire 4
            if (app()->bound('livewire.finder')) {
                return app('livewire.finder')->resolveClassComponentClassName($name);
            }

            // Livewire 3
            $registry = 'Livewire\\Mechanisms\\ComponentRegistry';

            if (class_exists($registry)) {
                return app($registry)->getClass($name);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
