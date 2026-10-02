# Laravel GlitchTip

Error tracking for Laravel with [GlitchTip](https://glitchtip.com) or any other Sentry compatible server.

The package builds on the official [`sentry/sentry-laravel`](https://github.com/getsentry/sentry-laravel) SDK and adds:

- **Privacy mode**: one switch (`SENTRY_PRIVACY_MODE`) decides whether personal data is sent
- **Release detection** without configuration: `REVISION` file written by [Deployer](https://deployer.org) or the checked out git commit
- **Context on every event**: user id, locale, tenant ([spatie/laravel-multitenancy](https://github.com/spatie/laravel-multitenancy)) and the involved Livewire components
- **Full client ip address** as resolved by Laravel (trusted proxies) instead of `REMOTE_ADDR`
- A hook to add project specific context

Supports Laravel 10 to 13 and Livewire 3 and 4.

## Installation

```bash
composer require ameax/laravel-glitchtip
php artisan sentry:publish --dsn=https://<key>@<your-glitchtip-host>/<project-id>
```

Report exceptions to GlitchTip in `bootstrap/app.php` (Laravel 11+):

```php
use Sentry\Laravel\Integration;

->withExceptions(function (Exceptions $exceptions) {
    Integration::handles($exceptions);
})
```

On Laravel 10 add this to the `register()` method of `app/Exceptions/Handler.php`:

```php
$this->reportable(function (Throwable $e) {
    \Sentry\Laravel\Integration::captureUnhandledException($e);
});
```

Optionally add a log channel to `config/logging.php`:

```php
'sentry' => [
    'driver' => 'sentry',
],
```

## Configuration

| Variable | Default | Description |
| --- | --- | --- |
| `SENTRY_LARAVEL_DSN` | | DSN of the GlitchTip project |
| `SENTRY_PRIVACY_MODE` | `false` | Do not send personal data |
| `SENTRY_MAX_REQUEST_BODY_SIZE` | `medium` | `none`, `small`, `medium` or `always` (ignored in privacy mode) |
| `SENTRY_RELEASE` | detected | Overrides the detected release |
| `SENTRY_DETECT_RELEASE` | `true` | Detect the release from `REVISION` or `.git` |
| `SENTRY_ENVIRONMENT` | `APP_ENV` | Environment of the events |
| `SENTRY_TRACES_SAMPLE_RATE` | | Share of requests traced for performance monitoring, e.g. `0.01` |

Publish the package config with `php artisan vendor:publish --tag=laravel-glitchtip-config` if needed.

The package sets `send_default_pii`, `max_request_body_size` and the SQL binding options of the Sentry config
based on the privacy mode. Configure them via the variables above, not in `config/sentry.php`.

### What is sent

| Data | Privacy mode off | Privacy mode on |
| --- | --- | --- |
| Exception, stack trace, URL, route, server name, environment, release | ✅ | ✅ |
| User id, locale, tenant | ✅ | ✅ |
| Livewire component class and called methods | ✅ | ✅ |
| Full client ip address, cookies, headers, session id | ✅ | ❌ |
| User email and name | ✅ | ❌ |
| Request body, SQL bindings, Livewire component data | ✅ | ❌ |

Note: GlitchTip truncates ip addresses unless "Scrub IP addresses" is disabled in the project settings.

### Release detection

When `SENTRY_RELEASE` is not set, the release is detected in this order:

1. `REVISION` file in the base path (written by Deployer into each release directory)
2. The checked out commit of `.git` (deployments via `git pull`)

Files are read directly, no shell command is executed.

## Project specific context

```php
use Ameax\Glitchtip\Glitchtip;
use Sentry\Event;

// e.g. in AppServiceProvider::boot()
Glitchtip::enrichUsing(function (Event $event): void {
    $event->setTag('installation', config('app.name'));
});
```

Exceptions thrown by an enricher are swallowed so that the event is still sent.

## Testing

```bash
composer test
composer analyse
```

## License

The MIT License (MIT). Please see [License File](LICENSE) for more information.
