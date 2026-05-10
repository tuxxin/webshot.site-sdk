<?php
// basic-capture.php — Webshot SDK quickstart in PHP.
// Install: composer require tuxxin/webshot
// Run:     php basic-capture.php
require __DIR__ . '/../php/vendor/autoload.php';   // adjust to wherever your vendor/ lives

use Tuxxin\Webshot\Client;
use Tuxxin\Webshot\Exception\ThrottledException;
use Tuxxin\Webshot\Exception\WebshotException;

$client = new Client();

try {
    $shot = $client->capture('https://example.com', format: 'png', mode: 'desktop_full');
    $shot->write('example.png');
    echo "✓ saved example.png (" . strlen($shot->bytes) . " bytes)\n";
} catch (ThrottledException $e) {
    echo "Throttled — retry in {$e->getRetryAfter()}s. Email {$e->getContact()} for higher limits.\n";
    exit(1);
} catch (WebshotException $e) {
    echo "Capture failed: {$e->getMessage()}\n";
    exit(1);
}
