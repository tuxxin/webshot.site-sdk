<?php
declare(strict_types=1);

namespace Tuxxin\Webshot\Exception;

/**
 * The Webshot API returned a 4xx/5xx response other than 429.
 * Inspect `getStatus()` for the HTTP code and `getDocsUrl()` for the
 * documentation pointer the API includes in error payloads.
 */
class ApiException extends WebshotException
{
    public function __construct(
        string $message,
        private readonly int $status,
        private readonly ?string $docsUrl = null,
    ) {
        parent::__construct($message);
    }

    public function getStatus(): int { return $this->status; }
    public function getDocsUrl(): ?string { return $this->docsUrl; }
}
