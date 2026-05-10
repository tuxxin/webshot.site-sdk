// basic-capture.js — Webshot SDK quickstart in Node.
// Install: npm install @tuxxin/webshot
// Run:     node basic-capture.js
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
  console.log(`✓ saved example.png (${shot.bytes.length} bytes)`);
} catch (err) {
  if (err instanceof WebshotThrottledError) {
    console.error(`Throttled — retry in ${err.retryAfter}s. Email ${err.contact} for higher limits.`);
  } else {
    console.error(`Capture failed: ${err.message}`);
  }
  process.exit(1);
}
