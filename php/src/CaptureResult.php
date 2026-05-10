<?php
declare(strict_types=1);

namespace Tuxxin\Webshot;

/**
 * Return value of `Client::capture()`.
 *
 * Holds the raw image bytes plus the rate-limit metadata Webshot reports
 * via response headers, so callers can pace subsequent requests.
 */
final class CaptureResult
{
    /**
     * @param string  $bytes        Raw image bytes — write straight to a file or stream upward.
     * @param string  $contentType  MIME type of $bytes (e.g. 'image/png', 'application/pdf').
     * @param ?string $source       Hostname of the URL that was captured (X-Webshot-Source).
     * @param ?string $mode         Capture mode actually used (X-Webshot-Mode).
     * @param ?int    $rateLimit    Total credits per window (X-RateLimit-Limit).
     * @param ?int    $rateRemaining Credits remaining after this call (X-RateLimit-Remaining).
     * @param ?int    $rateResetAt  Unix timestamp when the next credit refills (X-RateLimit-Reset).
     */
    public function __construct(
        public readonly string $bytes,
        public readonly string $contentType,
        public readonly ?string $source,
        public readonly ?string $mode,
        public readonly ?int $rateLimit,
        public readonly ?int $rateRemaining,
        public readonly ?int $rateResetAt,
    ) {
    }

    /** Convenience: write bytes to disk and return the byte count. */
    public function write(string $path): int
    {
        $written = file_put_contents($path, $this->bytes);
        if ($written === false) {
            throw new \RuntimeException("Could not write to {$path}");
        }
        return $written;
    }
}
