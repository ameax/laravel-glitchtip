# Changelog

All notable changes to `laravel-glitchtip` will be documented in this file.

## v0.1.2 - 2026-10-02

- Fix: enrichment and credential filter now run as `before_send` callback. As global event processor they ran before the request integration of the SDK, so request data was neither filtered nor used for the client ip address
- A `before_send` callback of the project is called afterwards

## v0.1.1 - 2026-10-02

- Always filter credentials: passwords, tokens and secrets in request body, query string, url, headers and Livewire context, the cookie header and all cookie values

## v0.1.0 - 2026-10-02

- Initial release: privacy mode, release detection (Deployer `REVISION` file and git), user id, locale, tenant and Livewire context, full client ip address, project specific enrichers
