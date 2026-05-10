<?php
declare(strict_types=1);

namespace Tuxxin\Webshot\Exception;

/**
 * Parent class for every exception thrown by the Webshot SDK.
 *
 * Catch this for "anything went wrong":
 *
 *   try { $client->capture('https://example.com'); }
 *   catch (\Tuxxin\Webshot\Exception\WebshotException $e) { ... }
 */
class WebshotException extends \RuntimeException
{
}
