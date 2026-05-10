"""basic.py — single capture, sync. Run: python examples/basic.py"""
import sys

from webshot import WebshotClient, WebshotThrottledError, WebshotError


def main() -> int:
    with WebshotClient() as client:
        try:
            shot = client.capture("https://example.com", format="png", mode="desktop_full")
        except WebshotThrottledError as e:
            print(f"Rate limit hit — retry in {e.retry_after}s.")
            print(f"For higher limits: email {e.contact}")
            return 2
        except WebshotError as e:
            print(f"Capture failed: {e}")
            return 1

        shot.write("example.png")
        print(f"✓ saved example.png  ({len(shot.bytes)} bytes, {shot.content_type})")
        print(f"  source={shot.source}  mode={shot.mode}")
        print(f"  credits remaining: {shot.rate_limit.remaining}/{shot.rate_limit.limit}")
        return 0


if __name__ == "__main__":
    sys.exit(main())
