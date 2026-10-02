<?php

namespace Ameax\Glitchtip;

use Ameax\Glitchtip\Enrichers\EventEnricher;
use Illuminate\Contracts\Config\Repository;
use Sentry\Event;
use Sentry\EventHint;

class Glitchtip
{
    /** @var array<int, callable(Event): void> */
    private static array $enrichers = [];

    /**
     * Registers a callback that adds project specific data to every event.
     *
     * @param  callable(Event): void  $callback
     */
    public static function enrichUsing(callable $callback): void
    {
        self::$enrichers[] = $callback;
    }

    /**
     * @return array<int, callable(Event): void>
     */
    public static function enrichers(): array
    {
        return self::$enrichers;
    }

    public static function flushEnrichers(): void
    {
        self::$enrichers = [];
    }

    public static function privacyMode(): bool
    {
        return (bool) config('glitchtip.privacy_mode');
    }

    /**
     * Derives the personal data related Sentry options from the privacy mode
     * and detects the release when none is configured.
     */
    public static function configureSentry(Repository $config, string $basePath): void
    {
        $privacyMode = (bool) $config->get('glitchtip.privacy_mode');

        $config->set([
            'sentry.send_default_pii' => ! $privacyMode,
            'sentry.max_request_body_size' => $privacyMode ? 'none' : $config->get('glitchtip.max_request_body_size', 'medium'),
            'sentry.breadcrumbs.sql_bindings' => ! $privacyMode,
            'sentry.tracing.sql_bindings' => ! $privacyMode,
        ]);

        if (empty($config->get('sentry.release')) && $config->get('glitchtip.detect_release')) {
            $config->set('sentry.release', Release::detect($basePath));
        }

        $beforeSend = $config->get('sentry.before_send');

        if ($beforeSend !== [self::class, 'beforeSend']) {
            $config->set([
                'glitchtip.project_before_send' => $beforeSend,
                'sentry.before_send' => [self::class, 'beforeSend'],
            ]);
        }
    }

    /**
     * Sentry runs `before_send` after all event processors (request data, user etc.), so the
     * enrichment and the credential filter see the complete event. A `before_send` callback
     * configured by the project is called afterwards.
     */
    public static function beforeSend(Event $event, ?EventHint $hint = null): ?Event
    {
        $event = app(EventEnricher::class)->enrich($event);
        $projectBeforeSend = config('glitchtip.project_before_send');

        return is_callable($projectBeforeSend) ? $projectBeforeSend($event, $hint) : $event;
    }
}
