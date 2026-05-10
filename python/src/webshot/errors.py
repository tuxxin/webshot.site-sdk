"""
Exception hierarchy raised by the Webshot SDK.

    WebshotError                  — abstract parent. `except WebshotError` catches all SDK errors.
        WebshotApiError           — server returned a 4xx/5xx other than 429.
        WebshotThrottledError     — server returned 429. Has `retry_after`, `contact`, etc.
"""
from __future__ import annotations

from typing import Optional


class WebshotError(Exception):
    """Parent class for every error this SDK raises. Catch this for "anything went wrong"."""


class WebshotApiError(WebshotError):
    """Server returned a 4xx/5xx response (other than 429)."""

    def __init__(self, message: str, status: int, docs_url: Optional[str] = None) -> None:
        super().__init__(message)
        self.status: int = status
        self.docs_url: Optional[str] = docs_url


class WebshotThrottledError(WebshotError):
    """Rate limit exceeded (HTTP 429). The exception carries the retry hint and contact info."""

    def __init__(
        self,
        message: str,
        *,
        retry_after: int,
        reset_at: Optional[int],
        limit: int,
        available: int,
        contact: str = "sales@tuxxin.com",
    ) -> None:
        super().__init__(message)
        self.retry_after: int = retry_after
        self.reset_at: Optional[int] = reset_at
        self.limit: int = limit
        self.available: int = available
        self.contact: str = contact
