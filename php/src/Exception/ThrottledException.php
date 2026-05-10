<?php
declare(strict_types=1);

namespace Tuxxin\Webshot\Exception;

/**
 * Rate limit exceeded (HTTP 429).
 *
 * The API returns the wait time as `Retry-After` (seconds) and provides the
 * sales contact email for users who need higher limits — both are exposed here
 * so you can surface a helpful message in your application without re-parsing
 * the underlying response.
 */
class ThrottledException extends WebshotException
{
    /**
     * @param string $message    Human-readable error message from the API.
     * @param int    $retryAfter Seconds until the next credit refills.
     * @param ?int   $resetAt    Unix timestamp (seconds) when the rate-limit window resets, or null.
     * @param int    $limit      Total credits per window.
     * @param int    $available  Credits available right now (typically 0).
     * @param string $contact    Email to ask for higher limits. Default: sales@tuxxin.com.
     */
    public function __construct(
        string $message,
        private readonly int $retryAfter,
        private readonly ?int $resetAt,
        private readonly int $limit,
        private readonly int $available,
        private readonly string $contact = 'sales@tuxxin.com',
    ) {
        parent::__construct($message);
    }

    public function getRetryAfter(): int { return $this->retryAfter; }
    public function getResetAt(): ?int   { return $this->resetAt; }
    public function getLimit(): int      { return $this->limit; }
    public function getAvailable(): int  { return $this->available; }
    public function getContact(): string { return $this->contact; }
}
