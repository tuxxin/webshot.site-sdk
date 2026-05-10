# Changelog

All notable changes to the Webshot SDK collection are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) · Versioning: [SemVer](https://semver.org/).

## [1.1.0] — 2026-05-12

Adds the `custom` capture mode (premium-only) and Bearer-token authentication across all three SDKs. Tablet modes (`tablet_full` / `tablet_viewport`) were already supported at the API level since 1.0.0; this release confirms them in the docs and examples.

### Added — JavaScript / TypeScript SDK
- `Mode` union now includes `'custom'`.
- `CaptureOptions` now accepts `width`, `height`, and `fullPage` (validated client-side: 320–3840 × 240–2160).
- `ClientOptions.apiKey` — optional premium API key, sent as `Authorization: Bearer <key>` on every request.
- Calling `capture({ mode: 'custom', ... })` without an `apiKey` throws `WebshotError` before the round-trip.

### Added — Python SDK
- `VALID_MODES` extended with `"custom"`. Module-level `CUSTOM_MIN_WIDTH` / `CUSTOM_MAX_WIDTH` / `CUSTOM_MIN_HEIGHT` / `CUSTOM_MAX_HEIGHT` constants exported.
- `WebshotClient` and `AsyncWebshotClient` now accept an `api_key=` keyword argument; sets the Authorization header on the underlying `httpx` client.
- `capture()` now accepts `width`, `height`, `full_page` keyword arguments; validation matches the API.

### Added — PHP SDK
- `Client::MODES` extended with `'custom'`. New constants `CUSTOM_MIN_WIDTH` / `CUSTOM_MAX_WIDTH` / `CUSTOM_MIN_HEIGHT` / `CUSTOM_MAX_HEIGHT`.
- `Client::__construct()` now accepts a 4th `?string $apiKey` argument; subclasses overriding `executeHttp()` automatically get the Bearer header.
- `capture()` now accepts `?int $width`, `?int $height`, `bool $fullPage` arguments.

### Notes
- All changes are **backward-compatible** — existing 1.0.0 callers continue to work unchanged.
- Premium keys are issued manually via `sales@tuxxin.com`. The standard desktop / tablet / mobile presets remain free for everyone.

## [1.0.0] — 2026-05-08

Initial public release across all three languages.

### Added — JavaScript / TypeScript SDK (`@tuxxin/webshot`)
- `WebshotClient` with `capture()` and `throttleStatus()` methods.
- Strongly-typed `CaptureOptions`, `CaptureResult`, `ThrottleStatus`, `Mode`, `Format`.
- Custom error classes: `WebshotError`, `WebshotApiError`, `WebshotThrottledError`.
- Works in Node 18+ (uses native `fetch`) and modern browsers.
- ESM + CJS dual-build via `tsup`.

### Added — Python SDK (`webshot`)
- `WebshotClient` (sync) and `AsyncWebshotClient` (async, `httpx`-based).
- Same method signatures: `capture(...)` returns `CaptureResult` with bytes + headers.
- Custom exceptions: `WebshotError`, `WebshotApiError`, `WebshotThrottledError`.
- Type hints throughout; mypy-clean.
- Compatible with Python 3.9+.

### Added — PHP SDK (`tuxxin/webshot`)
- `Tuxxin\Webshot\Client` with `capture()` and `throttleStatus()` methods.
- Custom exceptions: `WebshotException`, `ApiException`, `ThrottledException` (with `getRetryAfter()`).
- PSR-4 autoloading via Composer.
- Compatible with PHP 8.1+.
