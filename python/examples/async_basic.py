"""async_basic.py — single capture using AsyncWebshotClient.
Run: python examples/async_basic.py"""
import asyncio
import sys

from webshot import AsyncWebshotClient, WebshotThrottledError, WebshotError


async def main() -> int:
    async with AsyncWebshotClient() as client:
        try:
            shot = await client.capture("https://example.com", format="png")
        except WebshotThrottledError as e:
            print(f"Rate limit hit — retry in {e.retry_after}s.")
            print(f"For higher limits: email {e.contact}")
            return 2
        except WebshotError as e:
            print(f"Capture failed: {e}")
            return 1

        shot.write("example.png")
        print(f"✓ saved example.png  ({len(shot.bytes)} bytes, {shot.content_type})")
        return 0


if __name__ == "__main__":
    sys.exit(asyncio.run(main()))
