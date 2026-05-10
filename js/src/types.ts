/**
 * Public types for the @tuxxin/webshot SDK.
 * Mirrors the request/response shape of https://webshot.site/api/capture.
 */

/** Output format. PDF requires `mode` to be a Full mode. */
export type Format = 'jpg' | 'png' | 'webp' | 'pdf';

/**
 * Capture mode.
 *  - `*_full`     scrolls the entire document and stitches one tall image.
 *  - `*_viewport` captures only what fits above the fold at the viewport size.
 *  - `custom`     premium-only — caller supplies arbitrary `width` and `height`.
 *                 Requires a premium API key set via ClientOptions.apiKey.
 *
 * Viewport pixel sizes:
 *   desktop = 1920×1080  ·  tablet = 834×1194  ·  mobile = 390×844 (iPhone 15 Pro)
 *   custom  = 320–3840 wide  ·  240–2160 tall
 */
export type Mode =
  | 'desktop_full'
  | 'desktop_viewport'
  | 'tablet_full'
  | 'tablet_viewport'
  | 'mobile_full'
  | 'mobile_viewport'
  | 'custom';

/** Constructor options. */
export interface ClientOptions {
  /** Override the API host. Defaults to `https://webshot.site`. Useful for testing. */
  baseUrl?: string;
  /** HTTP request timeout in milliseconds. Defaults to 60_000 (60s). */
  timeoutMs?: number;
  /** Optional User-Agent string. Defaults to `@tuxxin/webshot/<version>`. */
  userAgent?: string;
  /** Optional pre-configured fetch implementation (for testing or custom transports). */
  fetch?: typeof globalThis.fetch;
  /**
   * Premium API key (sent as `Authorization: Bearer <key>`). Required for
   * `mode: 'custom'`. Email sales@tuxxin.com to provision one.
   */
  apiKey?: string;
}

/** Per-call capture options. `url` is required, others have sensible defaults. */
export interface CaptureOptions {
  /** Public HTTPS or HTTP URL to capture. Required. */
  url: string;
  /** Output format. Defaults to `'jpg'`. */
  format?: Format;
  /** Capture mode. Defaults to `'desktop_full'`. */
  mode?: Mode;
  /** Custom viewport width in pixels (320–3840). Required when `mode: 'custom'`. Premium only. */
  width?: number;
  /** Custom viewport height in pixels (240–2160). Required when `mode: 'custom'`. Premium only. */
  height?: number;
  /** When `mode: 'custom'`: scroll the entire document (true) or capture only the custom viewport (false, default). */
  fullPage?: boolean;
}

/** What `capture()` returns. */
export interface CaptureResult {
  /** Raw image bytes — write straight to disk, stream upward, etc. */
  bytes: Uint8Array;
  /** MIME type of the bytes (e.g. `'image/png'`, `'application/pdf'`). */
  contentType: string;
  /** Hostname of the URL that was captured (echoed by the API). */
  source: string | null;
  /** Capture mode that was actually used (echoed by the API). */
  mode: Mode | null;
  /** Snapshot of rate-limit state right after this call. */
  rateLimit: RateLimitInfo;
}

/** Throttle status returned by `throttleStatus()`. */
export interface ThrottleStatus {
  /** Captures available right now. */
  available: number;
  /** Captures used in the current window. */
  used: number;
  /** Total credits per window. */
  limit: number;
  /** Refill rate, captures per minute. */
  ratePerMinute: number;
  /** Unix timestamp (seconds) when the next credit refills, or null if at full. */
  nextReleaseAt: number | null;
}

/** Rate-limit headers parsed from a capture response. */
export interface RateLimitInfo {
  /** Total credits per window (parsed from `X-RateLimit-Limit`). */
  limit: number | null;
  /** Credits remaining after this call (parsed from `X-RateLimit-Remaining`). */
  remaining: number | null;
  /** Unix timestamp when the next credit refills (parsed from `X-RateLimit-Reset`). */
  resetAt: number | null;
}
