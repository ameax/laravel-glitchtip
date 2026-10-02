<?php

return [

    /*
     * Privacy mode: when enabled, no personal data is sent (request body, cookies, ip address,
     * session id, email and name of the user, SQL bindings, Livewire component data).
     *
     * The user id, the tenant, the locale and the Livewire component class are always sent.
     */
    'privacy_mode' => (bool) env('SENTRY_PRIVACY_MODE', false),

    /*
     * Maximum size of request bodies sent when the privacy mode is disabled.
     * Possible values: none, small, medium, always
     */
    'max_request_body_size' => env('SENTRY_MAX_REQUEST_BODY_SIZE', 'medium'),

    /*
     * Detect the release automatically when SENTRY_RELEASE is not set:
     * REVISION file (written by Deployer) or the checked out git commit.
     */
    'detect_release' => (bool) env('SENTRY_DETECT_RELEASE', true),

];
