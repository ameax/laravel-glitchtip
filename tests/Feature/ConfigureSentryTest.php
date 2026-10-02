<?php

use Ameax\Glitchtip\Glitchtip;

it('sends personal data when the privacy mode is disabled', function () {
    config(['glitchtip.privacy_mode' => false, 'glitchtip.max_request_body_size' => 'always']);

    Glitchtip::configureSentry(config(), base_path());

    expect(config('sentry.send_default_pii'))->toBeTrue()
        ->and(config('sentry.max_request_body_size'))->toBe('always')
        ->and(config('sentry.breadcrumbs.sql_bindings'))->toBeTrue()
        ->and(config('sentry.tracing.sql_bindings'))->toBeTrue();
});

it('does not send personal data in privacy mode', function () {
    config(['glitchtip.privacy_mode' => true]);

    Glitchtip::configureSentry(config(), base_path());

    expect(config('sentry.send_default_pii'))->toBeFalse()
        ->and(config('sentry.max_request_body_size'))->toBe('none')
        ->and(config('sentry.breadcrumbs.sql_bindings'))->toBeFalse()
        ->and(config('sentry.tracing.sql_bindings'))->toBeFalse();
});

it('detects the release only when none is configured', function () {
    $basePath = sys_get_temp_dir().'/glitchtip-config-'.uniqid();
    mkdir($basePath);
    file_put_contents($basePath.'/REVISION', 'abc123');

    config(['sentry.release' => null]);
    Glitchtip::configureSentry(config(), $basePath);
    expect(config('sentry.release'))->toBe('abc123');

    config(['sentry.release' => 'v1.2.3']);
    Glitchtip::configureSentry(config(), $basePath);
    expect(config('sentry.release'))->toBe('v1.2.3');

    unlink($basePath.'/REVISION');
    rmdir($basePath);
});

it('disables the privacy mode by default', function () {
    expect(config('glitchtip.privacy_mode'))->toBeFalse()
        ->and(config('sentry.send_default_pii'))->toBeTrue();
});
