# webshot — Python SDK

Official Python client for the [Webshot](https://webshot.site) screenshot API.

Capture any public URL as a PNG, JPG, WebP, or PDF — using a real headless Chrome browser running on Tuxxin's infrastructure.

## Install

```bash
pip install webshot
```

Requires **Python 3.9+**. Single dependency: `httpx`.

## Quickstart (sync)

```python
from webshot import WebshotClient

with WebshotClient() as client:
    shot = client.capture("https://example.com", format="png")
    shot.write("example.png")
    print(f"Saved {len(shot.bytes)} bytes ({shot.content_type}).")
    print(f"Credits left: {shot.rate_limit.remaining}/{shot.rate_limit.limit}")
```

## Quickstart (async)

```python
import asyncio
from webshot import AsyncWebshotClient

async def main():
    async with AsyncWebshotClient() as client:
        shot = await client.capture("https://example.com", format="png")
        shot.write("example.png")

asyncio.run(main())
```

## API

### `WebshotClient(*, base_url=..., timeout=60.0, user_agent=..., client=None)`

Construct a sync client. All keyword args are optional.

| Arg          | Default                          | Notes |
|---           |---                               |---    |
| `base_url`   | `'https://webshot.site'`         | Override for testing or self-hosting. |
| `timeout`    | `60.0`                           | Per-request timeout (seconds). Accepts `httpx.Timeout` for fine-grained control. |
| `user_agent` | `'webshot-python/<version>'`     | Sent as `User-Agent`. |
| `api_key`    | `None`                           | Premium API key. Sent as `Authorization: Bearer <key>`. Required for `mode='custom'`. Email sales@tuxxin.com to obtain one. |
| `client`     | `None`                           | Inject a pre-configured `httpx.Client` (advanced). |

`AsyncWebshotClient(...)` mirrors the same signature but accepts `httpx.AsyncClient`.

### `client.capture(url, *, format='jpg', mode='desktop_full') -> CaptureResult`

Returns:

```python
@dataclass(frozen=True)
class CaptureResult:
    bytes: bytes
    content_type: str
    source: Optional[str]
    mode: Optional[str]
    rate_limit: RateLimitInfo
    def write(self, path: str) -> int: ...
```

### `client.throttle_status() -> ThrottleStatus`

Returns the caller's current rate-limit bucket without spending a credit.

```python
@dataclass(frozen=True)
class ThrottleStatus:
    available: int
    used: int
    limit: int
    rate_per_minute: float
    next_release_at: Optional[int]   # unix timestamp (seconds)
```

## Modes & formats

| Format | Notes |
|---     |---    |
| `'jpg'`  | smallest file, easy sharing |
| `'png'`  | sharp text, lossless |
| `'webp'` | modern compression |
| `'pdf'`  | print-ready, vector text preserved |

| Mode                 | Viewport             | Description |
|---                   |---                   |---          |
| `'desktop_full'`     | 1920×1080            | Full-page desktop scroll. **Default.** |
| `'desktop_viewport'` | 1920×1080            | Above-the-fold desktop. |
| `'tablet_full'`      | 834×1194             | Full-page iPad-class. |
| `'tablet_viewport'`  | 834×1194             | Above-the-fold iPad-class. |
| `'mobile_full'`      | 390×844 (iPhone 15)  | Full-page mobile scroll. |
| `'mobile_viewport'`  | 390×844 (iPhone 15)  | Above-the-fold mobile. |
| `'custom'`           | 320–3840 × 240–2160  | **Premium-only.** Pass `width`, `height`, and (optional) `full_page=`. Requires `api_key=` on the client. |

### Custom resolution (premium)

```python
from webshot import WebshotClient

with WebshotClient(api_key="wsk_YOUR_PREMIUM_KEY") as client:
    shot = client.capture(
        "https://example.com",
        format="png",
        mode="custom",
        width=2560,
        height=1440,
        full_page=True,
    )
    shot.write("2k.png")
```

The `api_key` is sent as `Authorization: Bearer <key>` on every request. Email **[sales@tuxxin.com](mailto:sales@tuxxin.com?subject=Webshot%20—%20premium%20API%20key)** to provision one — usually same-day reply.

## Errors

```python
from webshot import WebshotClient, WebshotThrottledError, WebshotApiError, WebshotError

with WebshotClient() as client:
    try:
        shot = client.capture("https://example.com")
    except WebshotThrottledError as e:
        print(f"Throttled — retry in {e.retry_after}s.")
        print(f"For higher limits: email {e.contact}")
    except WebshotApiError as e:
        print(f"HTTP {e.status} — {e}")
    except WebshotError as e:
        print(f"Network or other failure: {e}")
```

All SDK exceptions inherit from `WebshotError` — catch the parent class for a generic "anything went wrong" handler.

## Rate limits

The free public API allows **5 captures per 15 minutes per IP address**.

For higher limits, dedicated capacity, or commercial plans, email **[sales@tuxxin.com](mailto:sales@tuxxin.com?subject=Webshot%20—%20higher%20rate%20limits)** — most requests get a same-day reply.

## Examples

The [`examples/`](./examples) folder has runnable scripts:

- [`basic.py`](./examples/basic.py) — single capture, save to disk
- [`batch.py`](./examples/batch.py) — capture many URLs serially with throttle handling
- [`async_basic.py`](./examples/async_basic.py) — async equivalent of `basic.py`

Run: `python examples/basic.py`

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
