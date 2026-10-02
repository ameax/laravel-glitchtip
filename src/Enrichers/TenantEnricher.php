<?php

namespace Ameax\Glitchtip\Enrichers;

use Sentry\Event;
use Throwable;

/**
 * Adds the current tenant of spatie/laravel-multitenancy (or any model with a static `current()` method
 * configured as `multitenancy.tenant_model`) as tags.
 *
 * spatie/laravel-multitenancy forgets the tenant of a queued job on `JobExceptionOccurred`, before the
 * worker reports the exception. Therefore the tenant made current while a job is processed is remembered
 * until the worker continues with the next job.
 */
class TenantEnricher
{
    private bool $processingJob = false;

    private ?object $jobTenant = null;

    public function enrich(Event $event): void
    {
        $tenant = $this->currentTenant() ?? ($this->processingJob ? $this->jobTenant : null);

        if ($tenant === null) {
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

    public function jobStarted(): void
    {
        $this->processingJob = true;
    }

    public function tenantMadeCurrent(object $tenant): void
    {
        $this->jobTenant = $tenant;
    }

    /**
     * Called once the worker continues with the next job (after the exception has been reported).
     */
    public function workerContinued(): void
    {
        $this->processingJob = false;
        $this->jobTenant = null;
    }

    private function currentTenant(): ?object
    {
        $tenantModel = config('multitenancy.tenant_model');

        if (! is_string($tenantModel) || ! class_exists($tenantModel) || ! method_exists($tenantModel, 'current')) {
            return null;
        }

        try {
            $tenant = $tenantModel::current();
        } catch (Throwable) {
            return null;
        }

        return is_object($tenant) ? $tenant : null;
    }
}
