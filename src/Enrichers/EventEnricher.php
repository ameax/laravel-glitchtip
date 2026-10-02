<?php

namespace Ameax\Glitchtip\Enrichers;

use Ameax\Glitchtip\Filters\SensitiveDataFilter;
use Ameax\Glitchtip\Glitchtip;
use Sentry\Event;
use Sentry\UserDataBag;
use Throwable;

/**
 * Adds context to every event sent to GlitchTip.
 *
 * Always: user id, locale, tenant and the Livewire components.
 * Only without privacy mode: the full client ip address, the session id and the Livewire component data.
 * Credentials (passwords, tokens, cookie header ...) are always filtered.
 */
class EventEnricher
{
    public function __construct(
        private readonly TenantEnricher $tenantEnricher,
        private readonly LivewireEnricher $livewireEnricher,
        private readonly SensitiveDataFilter $sensitiveDataFilter,
    ) {}

    public function enrich(Event $event): Event
    {
        $privacyMode = Glitchtip::privacyMode();

        $this->addUserId($event);
        $this->addLocale($event);
        $this->tenantEnricher->enrich($event);
        $this->livewireEnricher->enrich($event, includeData: ! $privacyMode);

        if (! $privacyMode) {
            $this->addIpAddress($event);
            $this->addSession($event);
        }

        foreach (Glitchtip::enrichers() as $enricher) {
            try {
                $enricher($event);
            } catch (Throwable) {
                continue;
            }
        }

        $this->sensitiveDataFilter->filter($event);

        return $event;
    }

    private function addUserId(Event $event): void
    {
        if ($event->getUser()?->getId() !== null) {
            return;
        }

        try {
            $userId = auth()->id();
        } catch (Throwable) {
            return;
        }

        if ($userId === null) {
            return;
        }

        $user = $event->getUser() ?? new UserDataBag;
        $event->setUser($user->setId($userId));
    }

    private function addLocale(Event $event): void
    {
        $event->setTag('locale', app()->getLocale());
    }

    /**
     * Uses the client ip resolved by Laravel (respecting trusted proxies) instead of REMOTE_ADDR.
     * Events from console commands and queued jobs carry no request data and get no ip address.
     */
    private function addIpAddress(Event $event): void
    {
        if ($event->getRequest() === []) {
            return;
        }

        try {
            $ipAddress = request()->ip();
        } catch (Throwable) {
            return;
        }

        if ($ipAddress === null) {
            return;
        }

        $user = $event->getUser() ?? new UserDataBag;
        $event->setUser($user->setIpAddress($ipAddress));
    }

    private function addSession(Event $event): void
    {
        try {
            $request = request();

            if (! $request->hasSession() || ! $request->session()->isStarted()) {
                return;
            }

            $event->setContext('session', ['id' => $request->session()->getId()]);
        } catch (Throwable) {
            return;
        }
    }
}
