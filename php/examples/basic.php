<?php
// basic.php — single capture, save to disk. Run: php examples/basic.php
require __DIR__ . '/../vendor/autoload.php';

use Tuxxin\Webshot\Client;
use Tuxxin\Webshot\Exception\ThrottledException;
use Tuxxin\Webshot\Exception\WebshotException;

$client = new Client();

try {
    $shot = $client->capture('https://example.com', format: 'png', mode: 'desktop_full');
} catch (ThrottledException $e) {
    fwrite(STDERR, "Rate limit hit — retry in {$e->getRetryAfter()}s.\n");
    fwrite(STDERR, "For higher limits: email {$e->getContact()}\n");
    exit(2);
} catch (WebshotException $e) {
    fwrite(STDERR, "Capture failed: {$e->getMessage()}\n");
    exit(1);
}

$shot->write('example.png');
echo "✓ saved example.png  (" . strlen($shot->bytes) . " bytes, {$shot->contentType})\n";
echo "  source={$shot->source}  mode={$shot->mode}\n";
echo "  credits remaining: {$shot->rateRemaining}/{$shot->rateLimit}\n";
