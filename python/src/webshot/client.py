"""
Webshot API clients (sync + async).

Both classes share the same method signatures; pick whichever fits your codebase.

    from webshot import WebshotClient
    client = WebshotClient()
    result = client.capture("https://example.com", format="png")
    open("out.png", "wb").write(result.bytes)

Async:

    from webshot import AsyncWebshotClient
    async with AsyncWebshotClient() as client:
        result = await client.capture("https://example.com", format="png")
"""
from __future__ import annotations

import json as _json
from dataclasses import dataclass
from typing import Any, Iterable, Mapping, Optional, Union

import httpx

from ._version import __version__
from .errors import WebshotApiError, WebshotError, WebshotThrottledError


# ── Public types ────────────────────────────────────────────────────────────

Format = str  # 'jpg' | 'png' | 'webp' | 'pdf'
Mode   = str  # 'desktop_full' | 'desktop_viewport' | 'tablet_full' | 'tablet_viewport' | 'mobile_full' | 'mobile_viewport'

VALID_FORMATS: tuple[str, ...] = ("jpg", "png", "webp", "pdf")
VALID_MODES: tuple[str, ...] = (
    "desktop_full", "desktop_viewport",
    "tablet_full",  "tablet_viewport",
    "mobile_full",  "mobile_viewport",
)


@dataclass(frozen=True)
class RateLimitInfo:
    """Snapshot of rate-limit headers from a single response."""
    limit: Optional[int]
    remaining: Optional[int]
    reset_at: Optional[int]


@dataclass(frozen=True)
class CaptureResult:
    """Return value of `client.capture()`."""
    bytes: bytes
    content_type: str
    source: Optional[str]
    mode: Optional[str]
    rate_limit: RateLimitInfo

    def write(self, path: str) -> int:
        """Convenience: write bytes to disk and return the byte count."""
        with open(path, "wb") as f:
            f.write(self.bytes)
        return len(self.bytes)


@dataclass(frozen=True)
class ThrottleStatus:
    """Return value of `client.throttle_status()`."""
    available: int
    used: int
    limit: int
    rate_per_minute: float
    next_release_at: Optional[int]


# ── Client implementations ──────────────────────────────────────────────────

DEFAULT_BASE_URL  = "https://webshot.site"
DEFAULT_TIMEOUT_S = 60.0
USER_AGENT        = f"webshot-python/{__version__} (+https://webshot.site)"


def _validate_capture_args(url: str, fmt: str, mode: str) -> None:
    if not isinstance(url, str) or not url:
        raise WebshotError("capture(): `url` must be a non-empty string.")
    if fmt not in VALID_FORMATS:
        raise WebshotError(f"capture(): invalid format {fmt!r}. Allowed: {', '.join(VALID_FORMATS)}.")
    if mode not in VALID_MODES:
        raise WebshotError(f"capture(): invalid mode {mode!r}. Allowed: {', '.join(VALID_MODES)}.")


def _to_int(v: Any) -> Optional[int]:
    try:
        if v is None or v == "":
            return None
        return int(v)
    except (TypeError, ValueError):
        return None


def _parse_rate_limit(headers: Mapping[str, str]) -> RateLimitInfo:
    return RateLimitInfo(
        limit     = _to_int(headers.get("X-RateLimit-Limit")),
        remaining = _to_int(headers.get("X-RateLimit-Remaining")),
        reset_at  = _to_int(headers.get("X-RateLimit-Reset")),
    )


def _capture_result_from(response: httpx.Response) -> CaptureResult:
    return CaptureResult(
        bytes        = response.content,
        content_type = response.headers.get("Content-Type", "application/octet-stream"),
        source       = response.headers.get("X-Webshot-Source"),
        mode         = response.headers.get("X-Webshot-Mode"),
        rate_limit   = _parse_rate_limit(response.headers),
    )


def _throttled_from(response: httpx.Response) -> WebshotThrottledError:
    payload = _safe_json(response) or {}
    return WebshotThrottledError(
        message     = str(payload.get("error") or "Webshot rate limit exceeded."),
        retry_after = _to_int(response.headers.get("Retry-After")) or 60,
        reset_at    = _to_int(payload.get("reset_at")),
        limit       = _to_int(payload.get("limit")) or 5,
        available   = _to_int(payload.get("available")) or 0,
        contact     = str(payload.get("contact") or "sales@tuxxin.com"),
    )


def _api_error_from(response: httpx.Response) -> WebshotApiError:
    payload = _safe_json(response) or {}
    return WebshotApiError(
        message  = str(payload.get("error") or f"Webshot API HTTP {response.status_code}"),
        status   = response.status_code,
        docs_url = str(payload["docs"]) if isinstance(payload.get("docs"), str) else None,
    )


def _safe_json(response: httpx.Response) -> Optional[dict]:
    try:
        data = response.json()
    except (ValueError, _json.JSONDecodeError):
        return None
    return data if isinstance(data, dict) else None


# ── Sync client ─────────────────────────────────────────────────────────────


class WebshotClient:
    """
    Synchronous client. Use as a context manager (or call `close()`) so the
    underlying httpx connection pool is released cleanly.

        with WebshotClient() as client:
            shot = client.capture("https://example.com", format="png")
    """

    def __init__(
        self,
        *,
        base_url: str = DEFAULT_BASE_URL,
        timeout: Union[float, httpx.Timeout] = DEFAULT_TIMEOUT_S,
        user_agent: str = USER_AGENT,
        client: Optional[httpx.Client] = None,
    ) -> None:
        self._base_url = base_url.rstrip("/")
        self._owns_client = client is None
        self._client = client or httpx.Client(
            timeout=timeout,
            headers={"User-Agent": user_agent},
            follow_redirects=False,
        )

    def __enter__(self) -> "WebshotClient":
        return self

    def __exit__(self, *exc: Any) -> None:
        self.close()

    def close(self) -> None:
        if self._owns_client:
            self._client.close()

    def capture(
        self,
        url: str,
        *,
        format: Format = "jpg",
        mode: Mode = "desktop_full",
    ) -> CaptureResult:
        """
        Capture a screenshot. Returns a `CaptureResult`.

        Raises:
            WebshotThrottledError: rate limit hit (HTTP 429).
            WebshotApiError:       any other 4xx/5xx response.
            WebshotError:          network errors, timeouts, malformed responses.
        """
        _validate_capture_args(url, format, mode)
        try:
            response = self._client.post(
                self._base_url + "/api/capture",
                json={"url": url, "format": format, "mode": mode},
                headers={"Accept": "image/*, application/pdf, application/json"},
            )
        except httpx.HTTPError as e:
            raise WebshotError(f"Webshot network error: {e}") from e

        if response.status_code == 429:
            raise _throttled_from(response)
        if response.status_code >= 400:
            raise _api_error_from(response)
        return _capture_result_from(response)

    def throttle_status(self) -> ThrottleStatus:
        """Get the caller's current rate-limit bucket without spending a credit."""
        try:
            response = self._client.get(self._base_url + "/throttle-status")
        except httpx.HTTPError as e:
            raise WebshotError(f"Webshot network error: {e}") from e

        if response.status_code >= 400:
            raise _api_error_from(response)

        payload = _safe_json(response) or {}
        return ThrottleStatus(
            available       = _to_int(payload.get("available"))         or 0,
            used            = _to_int(payload.get("used"))              or 0,
            limit           = _to_int(payload.get("limit"))             or 5,
            rate_per_minute = float(payload.get("rate_per_minute") or 0),
            next_release_at = _to_int(payload.get("next_release_at")),
        )


# ── Async client (mirror of the above) ──────────────────────────────────────


class AsyncWebshotClient:
    """
    Async client. Use as an async context manager:

        async with AsyncWebshotClient() as client:
            shot = await client.capture("https://example.com", format="png")
    """

    def __init__(
        self,
        *,
        base_url: str = DEFAULT_BASE_URL,
        timeout: Union[float, httpx.Timeout] = DEFAULT_TIMEOUT_S,
        user_agent: str = USER_AGENT,
        client: Optional[httpx.AsyncClient] = None,
    ) -> None:
        self._base_url = base_url.rstrip("/")
        self._owns_client = client is None
        self._client = client or httpx.AsyncClient(
            timeout=timeout,
            headers={"User-Agent": user_agent},
            follow_redirects=False,
        )

    async def __aenter__(self) -> "AsyncWebshotClient":
        return self

    async def __aexit__(self, *exc: Any) -> None:
        await self.aclose()

    async def aclose(self) -> None:
        if self._owns_client:
            await self._client.aclose()

    async def capture(
        self,
        url: str,
        *,
        format: Format = "jpg",
        mode: Mode = "desktop_full",
    ) -> CaptureResult:
        _validate_capture_args(url, format, mode)
        try:
            response = await self._client.post(
                self._base_url + "/api/capture",
                json={"url": url, "format": format, "mode": mode},
                headers={"Accept": "image/*, application/pdf, application/json"},
            )
        except httpx.HTTPError as e:
            raise WebshotError(f"Webshot network error: {e}") from e

        if response.status_code == 429:
            raise _throttled_from(response)
        if response.status_code >= 400:
            raise _api_error_from(response)
        return _capture_result_from(response)

    async def throttle_status(self) -> ThrottleStatus:
        try:
            response = await self._client.get(self._base_url + "/throttle-status")
        except httpx.HTTPError as e:
            raise WebshotError(f"Webshot network error: {e}") from e

        if response.status_code >= 400:
            raise _api_error_from(response)
        payload = _safe_json(response) or {}
        return ThrottleStatus(
            available       = _to_int(payload.get("available"))         or 0,
            used            = _to_int(payload.get("used"))              or 0,
            limit           = _to_int(payload.get("limit"))             or 5,
            rate_per_minute = float(payload.get("rate_per_minute") or 0),
            next_release_at = _to_int(payload.get("next_release_at")),
        )
