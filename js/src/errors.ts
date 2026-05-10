/**
 * Error hierarchy thrown by @tuxxin/webshot.
 *
 *   WebshotError                   — abstract parent. Catch this for "anything went wrong".
 *     ├── WebshotApiError          — server returned a 4xx/5xx other than 429.
 *     └── WebshotThrottledError    — server returned 429. Has retry_after info.
 */

export class WebshotError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'WebshotError';
  }
}

export class WebshotApiError extends WebshotError {
  /** HTTP status code from the API. */
  public readonly status: number;
  /** Documentation URL the API points to (if any). */
  public readonly docsUrl: string | null;

  constructor(message: string, status: number, docsUrl: string | null = null) {
    super(message);
    this.name = 'WebshotApiError';
    this.status = status;
    this.docsUrl = docsUrl;
  }
}

export class WebshotThrottledError extends WebshotError {
  /** Seconds until the next credit refills. */
  public readonly retryAfter: number;
  /** Unix timestamp (seconds) when the rate-limit window resets. */
  public readonly resetAt: number | null;
  /** Total credits per window. */
  public readonly limit: number;
  /** Available credits right now (typically 0 when this is thrown). */
  public readonly available: number;
  /** Sales contact email for higher-limit options. */
  public readonly contact: string;

  constructor(opts: {
    message: string;
    retryAfter: number;
    resetAt: number | null;
    limit: number;
    available: number;
    contact?: string;
  }) {
    super(opts.message);
    this.name = 'WebshotThrottledError';
    this.retryAfter = opts.retryAfter;
    this.resetAt = opts.resetAt;
    this.limit = opts.limit;
    this.available = opts.available;
    this.contact = opts.contact ?? 'sales@tuxxin.com';
  }
}
