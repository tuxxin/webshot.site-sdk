// batch.mjs — capture multiple URLs serially with proper throttle handling.
// Sleeps for the API-suggested retry-after when the rate limit is hit.
//
// Run:  node examples/batch.mjs
import { WebshotClient, WebshotThrottledError } from '@tuxxin/webshot';
import { writeFile } from 'node:fs/promises';

const URLS = [
  'https://example.com',
  'https://www.iana.org/help/example-domains',
  'https://en.wikipedia.org/wiki/Headless_browser',
];

const client = new WebshotClient();
const sleep  = (ms) => new Promise((r) => setTimeout(r, ms));

for (const url of URLS) {
  const slug = url.replace(/^https?:\/\//, '').replace(/[^a-z0-9]+/gi, '-').toLowerCase();
  let attempts = 0;

  while (attempts < 3) {
    try {
      const shot = await client.capture({ url, format: 'png' });
      await writeFile(`${slug}.png`, shot.bytes);
      console.log(`✓ ${slug}.png  (${shot.bytes.length} bytes, ${shot.rateLimit.remaining} credits left)`);
      break;
    } catch (err) {
      if (err instanceof WebshotThrottledError) {
        console.log(`  throttled — sleeping ${err.retryAfter}s before retrying ${url}`);
        await sleep((err.retryAfter + 1) * 1000);
        attempts += 1;
        continue;
      }
      console.error(`✗ ${url}: ${err.message}`);
      break;
    }
  }
}
