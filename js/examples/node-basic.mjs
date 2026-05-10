// node-basic.mjs — capture a single URL from Node and save to disk.
// Run:  node examples/node-basic.mjs
import { WebshotClient, WebshotThrottledError } from '@tuxxin/webshot';
import { writeFile } from 'node:fs/promises';

const client = new WebshotClient();

try {
  const shot = await client.capture({
    url:    'https://example.com',
    format: 'png',
    mode:   'desktop_full',
  });

  await writeFile('example.png', shot.bytes);
  console.log(`✓ saved example.png (${shot.bytes.length} bytes, ${shot.contentType})`);
  console.log(`  source=${shot.source} mode=${shot.mode}`);
  console.log(`  credits remaining: ${shot.rateLimit.remaining}/${shot.rateLimit.limit}`);
} catch (err) {
  if (err instanceof WebshotThrottledError) {
    console.error(`Rate limit hit — retry in ${err.retryAfter}s.`);
    console.error(`For higher limits: email ${err.contact}`);
    process.exit(2);
  }
  console.error(`Capture failed: ${err.message}`);
  process.exit(1);
}
