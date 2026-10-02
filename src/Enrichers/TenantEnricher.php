<?php

namespace Ameax\Glitchtip\Enrichers;

use Sentry\Event;
use Throwable;

/**
 * Adds the current tenant of spatie/laravel-multitenancy (or any model with a static `current()` method
 * configured as `multitenancy.tenant_model`) as tags.
 */
class TenantEnricher
{
    public function enrich(Event $event): void
    {
        $tenantModel = config('multitenancy.tenant_model');

        if (! is_string($tenantModel) || ! class_exists($tenantModel) || ! method_exists($tenantModel, 'current')) {
            return;
        }

        try {
            $tenant = $tenantModel::current();
        } catch (Throwable) {
            return;
        }

        if (! is_object($tenant)) {
            return;
        }

        $tenantId = method_exists($tenant, 'getKey') ? $tenant->getKey() : data_get($tenant, 'id');
        $tenantName = data_get($tenant, 'name');

        if (is_scalar($tenantId)) {
            $event->setTag('tenant_id', (string) $tenantId);
        }

        if (is_scalar($tenantName) || is_scalar($tenantId)) {
            $event->setTag('tenant', (string) ($tenantName ?? $tenantId));
        }
    }
}
