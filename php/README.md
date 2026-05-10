# tuxxin/webshot — PHP SDK

Official PHP client for the [Webshot](https://webshot.site) screenshot API.

Capture any public URL as a PNG, JPG, WebP, or PDF — using a real headless Chrome browser running on Tuxxin's infrastructure.

## Install

```bash
composer require tuxxin/webshot
```

Requires **PHP 8.1+** with the `curl` and `json` extensions (both standard).

## Quickstart

```php
<?php
require 'vendor/autoload.php';

use Tuxxin\Webshot\Client;

$client = new Client();

$shot = $client->capture('https://example.com', format: 'png', mode: 'desktop_full');
$shot->write('example.png');

echo "Saved {$shot->bytes} bytes ({$shot->contentType}).\n";
echo "Credits remaining: {$shot->rateRemaining}/{$shot->rateLimit}\n";
```

## API

### `new Client(string $baseUrl = 'https://webshot.site', int $timeoutS = 60, ?string $userAgent = null)`

| Arg          | Default                           | Notes |
|---           |---                                |---    |
| `$baseUrl`   | `'https://webshot.site'`          | Override for testing or self-hosting. |
| `$timeoutS`  | `60`                              | Per-request timeout (seconds). |
| `$userAgent` | `'tuxxin-webshot-php/<version>'`  | Sent as `User-Agent`. |
| `$apiKey`    | `null`                            | Premium API key. Sent as `Authorization: Bearer <key>`. Required for `mode='custom'`. Email sales@tuxxin.com to obtain one. |

Subclass `Client` and override the protected `executeHttp()` method to plug in a different transport (e.g. Guzzle, Symfony HttpClient).

### `$client->capture(string $url, string $format = 'jpg', string $mode = 'desktop_full'): CaptureResult`

Returns a `CaptureResult` with public readonly properties:

```php
$shot->bytes;          // string — raw image bytes
$shot->contentType;    // string — MIME type
$shot->source;         // ?string — X-Webshot-Source header
$shot->mode;           // ?string — X-Webshot-Mode header
$shot->rateLimit;      // ?int   — X-RateLimit-Limit
$shot->rateRemaining;  // ?int   — X-RateLimit-Remaining
$shot->rateResetAt;    // ?int   — X-RateLimit-Reset (unix timestamp)
$shot->write('out.png');  // convenience: writes to disk, returns byte count
```

### `$client->throttleStatus(): ThrottleStatus`

Returns the caller's current rate-limit bucket without spending a credit:

```php
$s = $client->throttleStatus();
$s->available;       // int — captures available right now
$s->used;            // int — captures used this window
$s->limit;           // int — total credits per window
$s->ratePerMinute;   // float — refill rate
$s->nextReleaseAt;   // ?int — unix timestamp of next refill
```

## Modes & formats

| Format  | Notes |
|---      |---    |
| `'jpg'`  | smallest file, easy sharing |
| `'png'`  | sharp text, lossless |
| `'webp'` | modern compression |
| `'pdf'`  | print-ready, vector text preserved |

| Mode                 | Viewport             |
|---                   |---                   |
| `'desktop_full'`     | 1920×1080 — default  |
| `'desktop_viewport'` | 1920×1080            |
| `'tablet_full'`      | 834×1194             |
| `'tablet_viewport'`  | 834×1194             |
| `'mobile_full'`      | 390×844 (iPhone 15)  |
| `'mobile_viewport'`  | 390×844 (iPhone 15)  |
| `'custom'`           | 320–3840 × 240–2160 — **premium-only**, requires `$apiKey` |

### Custom resolution (premium)

```php
use Tuxxin\Webshot\Client;

$client = new Client(apiKey: 'wsk_YOUR_PREMIUM_KEY');

$shot = $client->capture(
    'https://example.com',
    format:   'png',
    mode:     'custom',
    width:    2560,
    height:   1440,
    fullPage: true,
);
$shot->write('2k.png');
```

The `$apiKey` is sent as `Authorization: Bearer <key>` on every request. Email **[sales@tuxxin.com](mailto:sales@tuxxin.com?subject=Webshot%20—%20premium%20API%20key)** to provision one — usually same-day reply.

## Errors

```php
use Tuxxin\Webshot\Client;
use Tuxxin\Webshot\Exception\{WebshotException, ThrottledException, ApiException};

$client = new Client();

try {
    $shot = $client->capture('https://example.com');
} catch (ThrottledException $e) {
    echo "Throttled — retry in {$e->getRetryAfter()}s.\n";
    echo "For higher limits: email {$e->getContact()}\n";
} catch (ApiException $e) {
    echo "HTTP {$e->getStatus()} — {$e->getMessage()}\n";
} catch (WebshotException $e) {
    echo "Network or other failure: {$e->getMessage()}\n";
}
```

All SDK exceptions inherit from `WebshotException` — catch the parent class for a generic "anything went wrong" handler.

## Rate limits

The free public API allows **5 captures per 15 minutes per IP address**.

For higher limits, dedicated capacity, or commercial plans, email **[sales@tuxxin.com](mailto:sales@tuxxin.com?subject=Webshot%20—%20higher%20rate%20limits)** — most requests get a same-day reply.

## Examples

The [`examples/`](./examples) folder has runnable scripts:

- [`basic.php`](./examples/basic.php) — single capture, save to disk
- [`batch.php`](./examples/batch.php) — capture many URLs serially with throttle handling

Run: `php examples/basic.php`

## Links

- 🏠 [webshot.site](https://webshot.site) — the free public service
- 📚 [webshot.site/developers](https://webshot.site/developers) — bare API docs
- 🛠️ [tuxxin.com](https://tuxxin.com) — Tuxxin (maintainer)
- ✉️ [sales@tuxxin.com](mailto:sales@tuxxin.com?subject=Webshot%20—%20commercial) — commercial / higher limits
- 🐛 [Issues](https://github.com/tuxxin/webshot.site-sdk/issues)

## License

MIT — see [LICENSE](../LICENSE).

---

<sub>Built and maintained by [Tuxxin](https://tuxxin.com).</sub>
