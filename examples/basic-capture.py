"""basic-capture.py — Webshot SDK quickstart in Python.
Install: pip install webshot
Run:     python basic-capture.py
"""
import sys
from webshot import WebshotClient, WebshotThrottledError, WebshotError

with WebshotClient() as client:
    try:
        shot = client.capture("https://example.com", format="png", mode="desktop_full")
        shot.write("example.png")
        print(f"✓ saved example.png ({len(shot.bytes)} bytes)")
    except WebshotThrottledError as e:
        print(f"Throttled — retry in {e.retry_after}s. Email {e.contact} for higher limits.")
        sys.exit(1)
    except WebshotError as e:
        print(f"Capture failed: {e}")
        sys.exit(1)
