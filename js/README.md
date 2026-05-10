# @tuxxin/webshot

Official JavaScript / TypeScript client for the [Webshot](https://webshot.site) screenshot API.

Capture any public URL as a PNG, JPG, WebP, or PDF — using a real headless Chrome browser running on Tuxxin's infrastructure.

```
┌─────────────────────────────────────────────────┐
│   const shot = await client.capture({           │
│     url: 'https://example.com',                 │
│     format: 'png',                              │
│     mode: 'desktop_full',                       │
│   });                                           │
│   await writeFile('out.png', shot.bytes);       │
└─────────────────────────────────────────────────┘
```

## Install

```bash
npm install @tuxxin/webshot
# or
pnpm add @tuxxin/webshot
# or
yarn add @tuxxin/webshot
```

Requires **Node 18+** (uses native `fetch`). Works in modern browsers — ESM and CJS bundles are both shipped.

## Quickstart

### Node

```js
import { WebshotClient } from '@tuxxin/webshot';
import { writeFile } from 'node:fs/promises';

const client = new WebshotClient();

const shot = await client.capture({
  url:    'https://example.com',
  format: 'png',
  mode:   'desktop_full',
});

await writeFile('example.png', shot.bytes);
console.log(`Saved ${shot.bytes.length} bytes (${shot.contentType}).`);
console.log(`Credits remaining: ${shot.rateLimit.remaining}/${shot.rateLimit.limit}`);
```

### Browser

```html
<script type="module">
  import { WebshotClient } from 'https://esm.sh/@tuxxin/webshot';

  const client = new WebshotClient();
  const shot = await client.capture({ url: location.href, format: 'png' });

  // Render in an <img>
  const blob = new Blob([shot.bytes], { type: shot.contentType });
  document.getElementById('preview').src = URL.createObjectURL(blob);
</script>
```

## API

### `new WebshotClient(options?)`

| Option       | Type     | Default                                        | Notes |
|---           |---       |---                                             |---    |
| `baseUrl`    | `string` | `'https://webshot.site'`                       | Override for testing or self-hosting. |
| `timeoutMs`  | `number` | `60_000`                                       | Per-request timeout. |
| `userAgent`  | `string` | `'@tuxxin/webshot/<version>'`                  | Sent as `User-Agent`. |
| `fetch`      | `fetch`  | `globalThis.fetch`                             | Inject a custom fetch (testing, undici, etc.). |

### `client.capture(options): Promise<CaptureResult>`

| Option   | Type     | Default          | Notes |
|---       |---       |---               |---    |
| `url`    | `string` | **required**     | Public HTTP/HTTPS URL. |
| `format` | `Format` | `'jpg'`          | One of `'jpg' \| 'png' \| 'webp' \| 'pdf'`. |
| `mode`   | `Mode`   | `'desktop_full'` | See [Modes](#modes) below. |

Returns:

```ts
interface CaptureResult {
  bytes:       Uint8Array;       // raw image bytes
  contentType: string;           // MIME type
  source:      string | null;    // X-Webshot-Source header
  mode:        Mode | null;      // X-Webshot-Mode header
  rateLimit:   {
    limit:     number | null;
    remaining: number | null;
    resetAt:   number | null;    // unix timestamp seconds
  };
}
```

### `client.throttleStatus(): Promise<ThrottleStatus>`

Returns the caller's current rate-limit bucket without spending a credit:

```ts
interface ThrottleStatus {
  available:     number;          // captures available right now
  used:          number;          // captures used this window
  limit:         number;          // total credits per window
  ratePerMinute: number;          // refill rate
  nextReleaseAt: number | null;   // unix timestamp seconds
}
```

## Modes

| Mode               | Viewport             | Description |
|---                 |---                   |---          |
| `desktop_full`     | 1920×1080            | Full-page desktop scroll. **Default.** |
| `desktop_viewport` | 1920×1080            | Above-the-fold desktop. |
| `tablet_full`      | 834×1194             | Full-page iPad-class. |
| `tablet_viewport`  | 834×1194             | Above-the-fold iPad-class. |
| `mobile_full`      | 390×844 (iPhone 15)  | Full-page mobile scroll. |
| `mobile_viewport`  | 390×844 (iPhone 15)  | Above-the-fold mobile. |

## Errors

All errors extend `WebshotError`. Catch the parent class for "anything went wrong", or pattern-match on the subclass to handle each case:

```ts
import { WebshotClient, WebshotThrottledError, WebshotApiError } from '@tuxxin/webshot';

const client = new WebshotClient();

try {
  const shot = await client.capture({ url: 'https://example.com' });
} catch (err) {
  if (err instanceof WebshotThrottledError) {
    console.log(`Throttled. Retry in ${err.retryAfter}s.`);
    console.log(`Need higher limits? Email ${err.contact}`);
  } else if (err instanceof WebshotApiError) {
    console.log(`API said no: HTTP ${err.status} — ${err.message}`);
  } else {
    console.log(`Network or other error: ${err.message}`);
  }
}
```

## Rate limits

The free public API allows **5 captures per 15 minutes per IP address**. The same limit applies on the website at [webshot.site](https://webshot.site).

Need more? Email **[sales@tuxxin.com](mailto:sales@tuxxin.com?subject=Webshot%20—%20higher%20rate%20limits)** for higher rate limits, dedicated capacity, or commercial plans.

## Examples

The [`examples/`](./examples) folder has runnable scripts:

- [`node-basic.mjs`](./examples/node-basic.mjs) — single capture, save to disk
- [`browser.html`](./examples/browser.html) — capture from a webpage
- [`batch.mjs`](./examples/batch.mjs) — capture many URLs with throttle handling

Run: `node examples/node-basic.mjs`

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
