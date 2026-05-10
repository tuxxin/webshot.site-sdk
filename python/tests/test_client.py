"""
Unit tests for the Webshot Python SDK.

These tests do NOT hit the live API. They exercise the client logic by injecting
a mocked httpx.Client (or AsyncClient) via httpx's MockTransport, so they're
fast and deterministic. Run: `pytest`
"""
import json
import pytest
import httpx

from webshot import (
    AsyncWebshotClient,
    WebshotApiError,
    WebshotClient,
    WebshotError,
    WebshotThrottledError,
)


# ── Helpers ─────────────────────────────────────────────────────────────────

def _client_with(handler):
    """Build a sync WebshotClient backed by a MockTransport."""
    transport = httpx.MockTransport(handler)
    inner = httpx.Client(transport=transport, base_url="https://webshot.site")
    return WebshotClient(client=inner)


# ── capture() success path ──────────────────────────────────────────────────

def test_capture_success_returns_bytes_and_metadata():
    def handler(request: httpx.Request) -> httpx.Response:
        assert request.method == "POST"
        assert request.url.path == "/api/capture"
        body = json.loads(request.content)
        assert body == {"url": "https://example.com", "format": "png", "mode": "desktop_full"}
        return httpx.Response(
            200,
            content=b"\x89PNG\r\n\x1a\nfake-bytes",
            headers={
                "Content-Type": "image/png",
                "X-Webshot-Source": "example.com",
                "X-Webshot-Mode": "desktop_full",
                "X-RateLimit-Limit": "5",
                "X-RateLimit-Remaining": "4",
                "X-RateLimit-Reset": "1234567890",
            },
        )

    with _client_with(handler) as client:
        shot = client.capture("https://example.com", format="png")
        assert shot.bytes.startswith(b"\x89PNG")
        assert shot.content_type == "image/png"
        assert shot.source == "example.com"
        assert shot.mode == "desktop_full"
        assert shot.rate_limit.limit == 5
        assert shot.rate_limit.remaining == 4
        assert shot.rate_limit.reset_at == 1234567890


# ── 429 → WebshotThrottledError ─────────────────────────────────────────────

def test_capture_429_raises_throttled_with_retry_after():
    def handler(request: httpx.Request) -> httpx.Response:
        return httpx.Response(
            429,
            json={
                "error": "Rate limit exceeded.",
                "retry_after": 47,
                "reset_at": 1700000000,
                "limit": 5,
                "available": 0,
                "contact": "sales@tuxxin.com",
            },
            headers={"Retry-After": "47"},
        )

    with _client_with(handler) as client:
        with pytest.raises(WebshotThrottledError) as exc:
            client.capture("https://example.com")
        e = exc.value
        assert e.retry_after == 47
        assert e.reset_at == 1700000000
        assert e.contact == "sales@tuxxin.com"
        assert e.limit == 5
        assert e.available == 0


# ── 4xx (non-429) → WebshotApiError ─────────────────────────────────────────

def test_capture_400_raises_api_error():
    def handler(request: httpx.Request) -> httpx.Response:
        return httpx.Response(
            400,
            json={"error": "Missing required parameter: url", "docs": "https://webshot.site/developers"},
        )

    with _client_with(handler) as client:
        with pytest.raises(WebshotApiError) as exc:
            client.capture("https://example.com")
        assert exc.value.status == 400
        assert "Missing required parameter" in str(exc.value)
        assert exc.value.docs_url == "https://webshot.site/developers"


# ── argument validation runs locally (no HTTP) ──────────────────────────────

def test_capture_rejects_bad_format_locally():
    with WebshotClient() as client:
        with pytest.raises(WebshotError):
            client.capture("https://example.com", format="bmp")  # type: ignore[arg-type]


def test_capture_rejects_bad_mode_locally():
    with WebshotClient() as client:
        with pytest.raises(WebshotError):
            client.capture("https://example.com", mode="kiosk_full")  # type: ignore[arg-type]


def test_capture_rejects_empty_url_locally():
    with WebshotClient() as client:
        with pytest.raises(WebshotError):
            client.capture("")


# ── throttle_status() ───────────────────────────────────────────────────────

def test_throttle_status_returns_dataclass():
    def handler(request: httpx.Request) -> httpx.Response:
        assert request.method == "GET"
        assert request.url.path == "/throttle-status"
        return httpx.Response(
            200,
            json={
                "available": 3, "used": 2, "limit": 5,
                "rate_per_minute": 0.333,
                "next_release_at": 1700000600,
            },
        )

    with _client_with(handler) as client:
        s = client.throttle_status()
        assert s.available == 3
        assert s.used == 2
        assert s.limit == 5
        assert s.rate_per_minute == pytest.approx(0.333)
        assert s.next_release_at == 1700000600


# ── async parity check ─────────────────────────────────────────────────────

async def test_async_capture_success_returns_bytes():
    def handler(request: httpx.Request) -> httpx.Response:
        return httpx.Response(200, content=b"PNG-bytes",
                              headers={"Content-Type": "image/png"})

    transport = httpx.MockTransport(handler)
    inner = httpx.AsyncClient(transport=transport, base_url="https://webshot.site")
    async with AsyncWebshotClient(client=inner) as client:
        shot = await client.capture("https://example.com", format="png")
        assert shot.bytes == b"PNG-bytes"
        assert shot.content_type == "image/png"
