# Changelog

All notable changes to the Webshot SDK collection are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) · Versioning: [SemVer](https://semver.org/).

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
