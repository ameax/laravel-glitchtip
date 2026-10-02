<?php

namespace Ameax\Glitchtip\Filters;

use Sentry\Event;

/**
 * Replaces credentials in events with "[Filtered]", independent of the privacy mode:
 * passwords, tokens and secrets in the request body, query string, headers and in the
 * Livewire context (including the Livewire snapshots in the request body). Cookie values are always filtered.
 */
class SensitiveDataFilter
{
    public const FILTERED = '[Filtered]';

    /**
     * @param  list<string>  $sensitiveKeys  Case insensitive parts of keys whose values are filtered
     */
    public function __construct(private readonly array $sensitiveKeys) {}

    public function filter(Event $event): void
    {
        $request = $event->getRequest();

        if ($request !== []) {
            foreach (['data', 'headers'] as $part) {
                if (is_array($request[$part] ?? null)) {
                    $request[$part] = $this->filterArray($request[$part]);
                }
            }

            if (is_array($request['cookies'] ?? null)) {
                $request['cookies'] = array_map(fn (): string => self::FILTERED, $request['cookies']);
            }

            if (is_string($request['query_string'] ?? null)) {
                $request['query_string'] = $this->filterQueryString($request['query_string']);
            }

            if (is_string($request['url'] ?? null)) {
                $request['url'] = $this->filterUrl($request['url']);
            }

            $event->setRequest($request);
        }

        $livewire = $event->getContexts()['livewire'] ?? null;

        if (is_array($livewire)) {
            $event->setContext('livewire', $this->filterArray($livewire));
        }
    }

    public function isSensitive(string|int $key): bool
    {
        $key = str_replace('-', '_', strtolower((string) $key));

        foreach ($this->sensitiveKeys as $sensitiveKey) {
            if ($sensitiveKey !== '' && str_contains($key, strtolower($sensitiveKey))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function filterArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                $data[$key] = self::FILTERED;
            } elseif (is_array($value)) {
                $data[$key] = $this->filterArray($value);
            } elseif ($key === 'snapshot' && is_string($value)) {
                $data[$key] = $this->filterJson($value);
            }
        }

        return $data;
    }

    /**
     * Livewire sends the component state as JSON encoded snapshot.
     */
    private function filterJson(string $json): string
    {
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return $json;
        }

        return json_encode($this->filterArray($decoded), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: $json;
    }

    private function filterQueryString(string $queryString): string
    {
        $parts = explode('&', $queryString);

        foreach ($parts as $index => $part) {
            $key = urldecode(explode('=', $part, 2)[0]);

            if ($key !== '' && $this->isSensitive(preg_replace('/\[.*$/', '', $key) ?? $key)) {
                $parts[$index] = explode('=', $part, 2)[0].'='.urlencode(self::FILTERED);
            }
        }

        return implode('&', $parts);
    }

    private function filterUrl(string $url): string
    {
        $position = strpos($url, '?');

        if ($position === false) {
            return $url;
        }

        return substr($url, 0, $position + 1).$this->filterQueryString(substr($url, $position + 1));
    }
}
