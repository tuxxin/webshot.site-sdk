/**
 * @tuxxin/webshot — Official client for the Webshot screenshot API.
 *
 * Service:  https://webshot.site
 * Maintainer: Tuxxin (https://tuxxin.com)
 * License:  MIT
 */

export { WebshotClient } from './client.js';
export {
  WebshotError,
  WebshotApiError,
  WebshotThrottledError,
} from './errors.js';
export type {
  CaptureOptions,
  CaptureResult,
  ClientOptions,
  Format,
  Mode,
  RateLimitInfo,
  ThrottleStatus,
} from './types.js';
