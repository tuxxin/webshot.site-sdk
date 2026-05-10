"""batch.py — capture many URLs serially, sleeping the API-suggested retry-after on throttle.
Run: python examples/batch.py"""
import re
import sys
import time

from webshot import WebshotClient, WebshotThrottledError, WebshotError


URLS = [
    "https://example.com",
    "https://www.iana.org/help/example-domains",
    "https://en.wikipedia.org/wiki/Headless_browser",
]


def slugify(url: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", re.sub(r"^https?://", "", url).lower()).strip("-")


def main() -> int:
    with WebshotClient() as client:
        for url in URLS:
            slug = slugify(url)
            for attempt in range(3):
                try:
                    shot = client.capture(url, format="png")
                    shot.write(f"{slug}.png")
                    print(f"✓ {slug}.png  ({len(shot.bytes)} bytes, {shot.rate_limit.remaining} credits left)")
                    break
                except WebshotThrottledError as e:
                    print(f"  throttled — sleeping {e.retry_after}s before retrying {url}")
                    time.sleep(e.retry_after + 1)
                except WebshotError as e:
                    print(f"✗ {url}: {e}")
                    break
    return 0


if __name__ == "__main__":
    sys.exit(main())
