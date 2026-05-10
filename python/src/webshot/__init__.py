"""
webshot — official Python client for the Webshot screenshot API.

Service:    https://webshot.site
Maintainer: Tuxxin (https://tuxxin.com)
License:    MIT

Quickstart:
    from webshot import WebshotClient

    with WebshotClient() as client:
        shot = client.capture("https://example.com", format="png")
        shot.write("example.png")
"""
from ._version import __version__
from .client import (
    AsyncWebshotClient,
    CaptureResult,
    RateLimitInfo,
    ThrottleStatus,
    WebshotClient,
    VALID_FORMATS,
    VALID_MODES,
)
from .errors import WebshotApiError, WebshotError, WebshotThrottledError

__all__ = [
    "__version__",
    "WebshotClient",
    "AsyncWebshotClient",
    "CaptureResult",
    "RateLimitInfo",
    "ThrottleStatus",
    "VALID_FORMATS",
    "VALID_MODES",
    "WebshotError",
    "WebshotApiError",
    "WebshotThrottledError",
]
