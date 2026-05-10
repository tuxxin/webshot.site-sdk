<?php
// batch.php — capture multiple URLs serially with throttle handling.
// Run: php examples/batch.php
require __DIR__ . '/../vendor/autoload.php';

use Tuxxin\Webshot\Client;
use Tuxxin\Webshot\Exception\ThrottledException;
use Tuxxin\Webshot\Exception\WebshotException;

$urls = [
    'https://example.com',
    'https://www.iana.org/help/example-domains',
    'https://en.wikipedia.org/wiki/Headless_browser',
];

$client = new Client();

foreach ($urls as $url) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', preg_replace('/^https?:\/\//', '', $url)));
    $slug = trim($slug, '-');

    for ($attempt = 0; $attempt < 3; $attempt++) {
        try {
            $shot = $client->capture($url, format: 'png');
            $shot->write("{$slug}.png");
            $remaining = $shot->rateRemaining ?? '?';
            echo "✓ {$slug}.png  (" . strlen($shot->bytes) . " bytes, {$remaining} credits left)\n";
            continue 2;
        } catch (ThrottledException $e) {
            $wait = $e->getRetryAfter() + 1;
            echo "  throttled — sleeping {$wait}s before retrying {$url}\n";
            sleep($wait);
        } catch (WebshotException $e) {
            echo "✗ {$url}: {$e->getMessage()}\n";
            continue 2;
        }
    }
}
