# Webshot SDK

> Official client libraries for the [Webshot](https://webshot.site) screenshot API — capture any public URL as a PNG, JPG, WebP, or PDF, in three lines of code.

A free, no-account screenshot service maintained by **[Tuxxin](https://tuxxin.com)**. The same engine that powers [webshot.site](https://webshot.site) is exposed as a public HTTP API; this repo holds the official client libraries for **JavaScript/TypeScript**, **Python**, and **PHP**.

```
┌──────────────────────────────────────────────────────────────────┐
│  Your code  ──HTTPS──►  https://webshot.site/api/capture         │
│                          │                                       │
│                          │  Headless Chrome (full browser)       │
│                          ▼                                       │
│                          PNG / JPG / WebP / PDF  ◄── your file   │
└──────────────────────────────────────────────────────────────────┘
```

## Quick start

Pick your language. Each link goes to the per-language README with install + 5-line quickstart.

| SDK | Install | Docs |
|---|---|---|
| **JavaScript / TypeScript** | `npm install @tuxxin/webshot` | [`./js/README.md`](./js/README.md) |
| **Python** | `pip install webshot` | [`./python/README.md`](./python/README.md) |
| **PHP** | `composer require tuxxin/webshot` | [`./php/README.md`](./php/README.md) |

> The SDK is a thin wrapper around a JSON HTTP API. If your language isn't listed, see the bare API docs at [webshot.site/developers](https://webshot.site/developers) — the API is fully documented with `curl`, Python, Node, PHP, and Go examples.

## What is Webshot?

[Webshot](https://webshot.site) is a free service that takes a screenshot of any public URL using a real headless Chrome browser running on Tuxxin's infrastructure. No browser install, no Puppeteer setup, no Chromium binary management. You make one HTTP call and get back the rendered image.

It is built and maintained by **[Tuxxin](https://tuxxin.com)** — small US-based IT consultancy that runs the Webshot service alongside its other tools (the [Tuxxin Suite](https://tuxxin.com)).

### What it captures

- **Formats:** PNG, JPG, WebP, PDF
- **Viewports:** Desktop (1920×1080), Tablet (834×1194), Mobile (390×844 — iPhone 15 Pro)
- **Modes:** *Full* (entire scrolling document, top-to-bottom) or *Viewport* (above-the-fold only)
- **Rendering:** real headless Chrome — JavaScript executes, fonts load, lazy-loaded images resolve, SPAs render

### What it doesn't do

- ❌ **Authenticated pages** — the public API can't log in. Higher-tier plans support custom request headers; email [sales@tuxxin.com](mailto:sales@tuxxin.com) to discuss.
- ❌ **Pages behind aggressive bot-shields** (Cloudflare attack-mode, Akamai bot-defender) — typically capturable, occasionally not.
- ❌ **Pages requiring user interaction** (clicking, scrolling to a specific anchor, filling a form before content loads).

### Rate limits

The free public API allows **5 captures per 15 minutes per IP address** — enough for ad-hoc and small-scale usage. The same limit applies on the website at [webshot.site](https://webshot.site).

For higher rate limits, dedicated capacity, custom request headers, or commercial plans, email **[sales@tuxxin.com](mailto:sales@tuxxin.com?subject=Webshot%20—%20higher%20rate%20limits)** — most requests get a same-day reply with options for your use case.

## How it works

```
1. Your client calls         POST https://webshot.site/api/capture
                             {"url": "https://example.com",
                              "format": "png",
                              "mode": "desktop_full"}

2. Webshot validates the request (URL allowed? Format known? Throttle ok?).

3. Webshot spins up a headless Chrome instance, navigates to the URL,
   waits for fonts.ready + network-idle + lazy-loaded images, then
   captures using Chrome's native screenshot pipeline.

4. The rendered image streams back as the HTTP response body. Your SDK
   wraps it in a `CaptureResult` (bytes + content-type + rate-limit
   headers) and returns it.

5. The screenshot file is deleted from Webshot's server immediately
   after streaming. Webshot keeps no copy.
```

### Response anatomy

The API returns the image **as the raw HTTP body** (not base64-wrapped, not JSON-encoded). The SDKs hand it back to you as a `bytes` / `Buffer` / `Uint8Array` ready to write to disk or stream upward.

Every response carries rate-limit headers so you can adjust your capture cadence:

| Header | Meaning |
|---|---|
| `X-RateLimit-Limit` | Total credits per 15-minute window (default: 5) |
| `X-RateLimit-Remaining` | Credits left after this call |
| `X-RateLimit-Reset` | Unix timestamp when the next credit refills |
| `Retry-After` | (Throttled responses only) seconds to wait before retrying |
| `X-Webshot-Source` | Hostname of the URL that was captured |
| `X-Webshot-Mode` | The capture mode that was used |

### Errors

The SDKs raise typed exceptions instead of returning error objects, so you can handle each failure mode cleanly:

| Exception (PHP / Python / TS) | Cause |
|---|---|
| `ThrottledException` / `WebshotThrottledError` | HTTP 429 — you've burned all 5 credits this window. The exception exposes `retry_after` (seconds) and the contact email so you can respond gracefully. |
| `ApiException` / `WebshotApiError` | HTTP 4xx/5xx other than 429 — bad URL, invalid format, server error, etc. |
| `WebshotException` / `WebshotError` | Network problems, timeouts, malformed responses. Catch this as the parent class. |

## Examples

The cross-language [`./examples/`](./examples) directory has matching one-screen examples in each SDK:

- [`basic-capture.js`](./examples/basic-capture.js)
- [`basic-capture.py`](./examples/basic-capture.py)
- [`basic-capture.php`](./examples/basic-capture.php)

Each language also has a per-SDK `examples/` folder with batch-capture, throttle-handling, and (for JS) a browser-side example.

## Use-cases the team hears about most

These get covered with a dedicated landing page on [webshot.site/use-cases](https://webshot.site/use-cases):

- 🗺️ [Google Maps + business listings](https://webshot.site/use-cases/google-maps-screenshot) — local-SEO photo grabs
- 📝 [WordPress + Elementor previews](https://webshot.site/use-cases/wordpress-screenshot) — design QA + client review
- 🚀 [Landing-page QA](https://webshot.site/use-cases/landing-page-screenshot) — before/after pairs across viewports
- ⚖️ [Legal-evidence captures](https://webshot.site/use-cases/legal-evidence-screenshot) — preserve a page before it changes
- 💬 [Social-media post screenshots](https://webshot.site/use-cases/social-media-screenshot) — testimonials & marketing assets
- 🔍 [Competitor monitoring](https://webshot.site/use-cases/competitor-screenshot) — weekly visual changelogs
- 📩 [Email-friendly thumbnails](https://webshot.site/use-cases/email-screenshot) — paste straight into outbound emails
- 🔬 [Visual regression testing](https://webshot.site/use-cases/visual-regression-screenshot) — diff staging vs production in CI

## Repo layout

```
Webshot-SDK/
├── README.md                ← you are here
├── LICENSE                  ← MIT
├── CHANGELOG.md
├── examples/                ← cross-language quickstarts
├── js/                      ← TypeScript SDK (npm: @tuxxin/webshot)
│   ├── src/
│   ├── examples/
│   └── README.md
├── python/                  ← Python SDK (pip: webshot)
│   ├── src/webshot/
│   ├── examples/
│   └── README.md
└── php/                     ← PHP SDK (composer: tuxxin/webshot)
    ├── src/
    ├── examples/
    └── README.md
```

## Contributing

Bug reports, feature requests, and pull requests are welcome. For non-trivial changes please open an issue first to discuss the approach.

The SDKs are intentionally thin — most logic lives in the API itself. New SDK features should map to existing API features rather than introduce client-side state or transformation.

## Support & contact

- 📚 **API docs:** [webshot.site/developers](https://webshot.site/developers)
- 💬 **Free public service:** [webshot.site](https://webshot.site)
- 🛠️ **Other tools by Tuxxin:** [tuxxin.com](https://tuxxin.com)
- ✉️ **Higher rate limits / commercial use:** [sales@tuxxin.com](mailto:sales@tuxxin.com?subject=Webshot%20SDK%20—%20higher%20limits)
- 🐛 **Bugs / feature requests:** open a GitHub issue in this repo

## License

MIT — see [LICENSE](./LICENSE). Use it for personal projects, commercial products, or anything in between.

---

<sub>Built and maintained by [Tuxxin](https://tuxxin.com) · Affordable IT solutions since 2011 · [tuxxin.com](https://tuxxin.com) · [contact@tuxxin.com](mailto:contact@tuxxin.com)</sub>
