import {
  CaptureOptions,
  CaptureResult,
  ClientOptions,
  Format,
  Mode,
  RateLimitInfo,
  ThrottleStatus,
} from './types.js';
import {
  WebshotApiError,
  WebshotError,
  WebshotThrottledError,
} from './errors.js';

const SDK_VERSION = '1.1.0';
const DEFAULT_BASE_URL = 'https://webshot.site';
const DEFAULT_TIMEOUT_MS = 60_000;

const VALID_FORMATS: Format[] = ['jpg', 'png', 'webp', 'pdf'];
const VALID_MODES: Mode[] = [
  'desktop_full', 'desktop_viewport',
  'tablet_full',  'tablet_viewport',
  'mobile_full',  'mobile_viewport',
  'custom',
];

const CUSTOM_MIN_W = 320;
const CUSTOM_MAX_W = 3840;
const CUSTOM_MIN_H = 240;
const CUSTOM_MAX_H = 2160;

/**
 * Webshot API client.
 *
 * @example
 *   import { WebshotClient } from '@tuxxin/webshot';
 *   import { writeFile } from 'node:fs/promises';
 *
 *   const client = new WebshotClient();
 *   const shot = await client.capture({ url: 'https://example.com', format: 'png' });
 *   await writeFile('out.png', shot.bytes);
 */
export class WebshotClient {
  private readonly baseUrl: string;
  private readonly timeoutMs: number;
  private readonly userAgent: string;
  private readonly fetchImpl: typeof globalThis.fetch;
  private readonly apiKey: string | undefined;

  constructor(opts: ClientOptions = {}) {
    this.baseUrl   = (opts.baseUrl ?? DEFAULT_BASE_URL).replace(/\/+$/, '');
    this.timeoutMs = opts.timeoutMs ?? DEFAULT_TIMEOUT_MS;
    this.userAgent = opts.userAgent ?? `@tuxxin/webshot/${SDK_VERSION} (+https://webshot.site)`;
    this.fetchImpl = opts.fetch ?? globalThis.fetch;
    this.apiKey    = opts.apiKey;
    if (typeof this.fetchImpl !== 'function') {
      throw new WebshotError(
        'No fetch implementation available. Use Node 18+ or pass `fetch` in ClientOptions.',
      );
    }
  }

  /**
   * Capture a screenshot. Returns the raw image bytes plus rate-limit metadata.
   *
   * @throws {WebshotThrottledError} if the rate limit is hit (HTTP 429).
   * @throws {WebshotApiError}       on any other 4xx/5xx response from the API.
   * @throws {WebshotError}          on network errors, timeouts, or malformed responses.
   */
  async capture(opts: CaptureOptions): Promise<CaptureResult> {
    if (!opts.url || typeof opts.url !== 'string') {
      throw new WebshotError('capture(): `url` is required and must be a string.');
    }
    const format = opts.format ?? 'jpg';
    const mode   = opts.mode   ?? 'desktop_full';

    if (!VALID_FORMATS.includes(format)) {
      throw new WebshotError(
        `capture(): invalid format "${format}". Allowed: ${VALID_FORMATS.join(', ')}.`,
      );
    }
    if (!VALID_MODES.includes(mode)) {
      throw new WebshotError(
        `capture(): invalid mode "${mode}". Allowed: ${VALID_MODES.join(', ')}.`,
      );
    }

    // Custom-mode validation runs client-side too — fail fast before round-trip.
    const body: Record<string, unknown> = { url: opts.url, format, mode };
    if (mode === 'custom') {
      if (typeof opts.width !== 'number' || typeof opts.height !== 'number') {
        throw new WebshotError(
          'capture(): `width` and `height` are required when mode is "custom".',
        );
      }
      if (opts.width < CUSTOM_MIN_W || opts.width > CUSTOM_MAX_W ||
          opts.height < CUSTOM_MIN_H || opts.height > CUSTOM_MAX_H) {
        throw new WebshotError(
          `capture(): custom dimensions out of range. Width ${CUSTOM_MIN_W}-${CUSTOM_MAX_W}, height ${CUSTOM_MIN_H}-${CUSTOM_MAX_H}.`,
        );
      }
      if (this.apiKey === undefined || this.apiKey === '') {
        throw new WebshotError(
          'capture(): mode="custom" requires a premium `apiKey` in ClientOptions. Email sales@tuxxin.com to obtain one.',
        );
      }
      body.width     = opts.width;
      body.height    = opts.height;
      body.full_page = !!opts.fullPage;
    }

    const response = await this.request('/api/capture', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    });

    if (response.status === 429) {
      const json = await safeJson(response);
      throw new WebshotThrottledError({
        message: typeof json?.error === 'string'
          ? json.error
          : 'Webshot rate limit exceeded.',
        retryAfter: parseInt(response.headers.get('Retry-After') ?? '', 10) || 60,
        resetAt:    asNumber(json?.reset_at),
        limit:      asNumber(json?.limit) ?? 5,
        available:  asNumber(json?.available) ?? 0,
        contact:    typeof json?.contact === 'string' ? json.contact : 'sales@tuxxin.com',
      });
    }

    if (!response.ok) {
      const json = await safeJson(response);
      throw new WebshotApiError(
        typeof json?.error === 'string' ? json.error : `Webshot API HTTP ${response.status}`,
        response.status,
        typeof json?.docs === 'string' ? json.docs : null,
      );
    }

    const buffer = new Uint8Array(await response.arrayBuffer());
    return {
      bytes: buffer,
      contentType: response.headers.get('Content-Type') ?? 'application/octet-stream',
      source:      response.headers.get('X-Webshot-Source'),
      mode:        (response.headers.get('X-Webshot-Mode') as Mode | null) ?? null,
      rateLimit:   parseRateLimit(response.headers),
    };
  }

  /** Get the caller's current throttle bucket state. Useful for paced capture loops. */
  async throttleStatus(): Promise<ThrottleStatus> {
    const response = await this.request('/throttle-status', { method: 'GET' });
    if (!response.ok) {
      throw new WebshotApiError(
        `Webshot throttle-status HTTP ${response.status}`,
        response.status,
      );
    }
    const json = await response.json();
    return {
      available:     asNumber(json.available)         ?? 0,
      used:          asNumber(json.used)              ?? 0,
      limit:         asNumber(json.limit)             ?? 5,
      ratePerMinute: asNumber(json.rate_per_minute)   ?? 0,
      nextReleaseAt: asNumber(json.next_release_at),
    };
  }

  /** Internal: fetch with timeout + UA + optional Bearer auth, normalizing network errors. */
  private async request(path: string, init: RequestInit): Promise<Response> {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeoutMs);
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json, image/*, application/pdf');
    headers.set('User-Agent', this.userAgent);
    if (this.apiKey !== undefined && this.apiKey !== '') {
      headers.set('Authorization', `Bearer ${this.apiKey}`);
    }
    try {
      return await this.fetchImpl(this.baseUrl + path, {
        ...init,
        headers,
        signal: controller.signal,
      });
    } catch (err) {
      if (err instanceof Error && err.name === 'AbortError') {
        throw new WebshotError(`Webshot request timed out after ${this.timeoutMs}ms`);
      }
      throw new WebshotError(`Webshot network error: ${(err as Error).message}`);
    } finally {
      clearTimeout(timer);
    }
  }
}

// ── small helpers (not exported) ──────────────────────────────────────────────

function asNumber(v: unknown): number | null {
  if (typeof v === 'number' && Number.isFinite(v)) return v;
  if (typeof v === 'string' && v.trim() !== '' && !Number.isNaN(Number(v))) return Number(v);
  return null;
}

async function safeJson(r: Response): Promise<any | null> {
  try { return await r.json(); } catch { return null; }
}

function parseRateLimit(headers: Headers): RateLimitInfo {
  return {
    limit:     asNumber(headers.get('X-RateLimit-Limit')),
    remaining: asNumber(headers.get('X-RateLimit-Remaining')),
    resetAt:   asNumber(headers.get('X-RateLimit-Reset')),
  };
}
