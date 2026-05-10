<?php
declare(strict_types=1);

namespace Tuxxin\Webshot;

use Tuxxin\Webshot\Exception\ApiException;
use Tuxxin\Webshot\Exception\ThrottledException;
use Tuxxin\Webshot\Exception\WebshotException;

/**
 * Webshot API client.
 *
 * Quickstart:
 *
 *   $client = new \Tuxxin\Webshot\Client();
 *   $shot   = $client->capture('https://example.com', format: 'png');
 *   $shot->write('out.png');
 *
 * The client uses the cURL extension directly (PHP 8.1+ standard ext) — no
 * Guzzle / Symfony HttpClient dependency. If you want to inject a different
 * transport, subclass and override `executeHttp()`.
 */
class Client
{
    public const VERSION = '1.0.0';

    public const FORMATS = ['jpg', 'png', 'webp', 'pdf'];
    public const MODES   = [
        'desktop_full', 'desktop_viewport',
        'tablet_full',  'tablet_viewport',
        'mobile_full',  'mobile_viewport',
    ];

    private string $baseUrl;
    private int $timeoutS;
    private string $userAgent;

    public function __construct(
        string $baseUrl   = 'https://webshot.site',
        int    $timeoutS  = 60,
        ?string $userAgent = null,
    ) {
        $this->baseUrl   = rtrim($baseUrl, '/');
        $this->timeoutS  = $timeoutS;
        $this->userAgent = $userAgent ?? 'tuxxin-webshot-php/' . self::VERSION . ' (+https://webshot.site)';
    }

    /**
     * Capture a screenshot.
     *
     * @param string $url    Public HTTP/HTTPS URL to capture.
     * @param string $format One of self::FORMATS. Default 'jpg'.
     * @param string $mode   One of self::MODES.   Default 'desktop_full'.
     *
     * @throws ThrottledException On HTTP 429 (rate limit hit).
     * @throws ApiException       On any other 4xx/5xx response.
     * @throws WebshotException   On network errors, timeouts, or malformed responses.
     */
    public function capture(
        string $url,
        string $format = 'jpg',
        string $mode   = 'desktop_full',
    ): CaptureResult {
        if ($url === '') {
            throw new WebshotException('capture(): $url must be a non-empty string.');
        }
        if (!in_array($format, self::FORMATS, true)) {
            throw new WebshotException(
                "capture(): invalid format '{$format}'. Allowed: " . implode(', ', self::FORMATS) . '.'
            );
        }
        if (!in_array($mode, self::MODES, true)) {
            throw new WebshotException(
                "capture(): invalid mode '{$mode}'. Allowed: " . implode(', ', self::MODES) . '.'
            );
        }

        $payload = json_encode(
            ['url' => $url, 'format' => $format, 'mode' => $mode],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
        );

        [$status, $body, $headers] = $this->executeHttp(
            'POST',
            $this->baseUrl . '/api/capture',
            $payload,
            ['Content-Type: application/json', 'Accept: image/*, application/pdf, application/json'],
        );

        if ($status === 429) {
            $json = $this->safeJson($body);
            throw new ThrottledException(
                message:    is_string($json['error'] ?? null) ? $json['error'] : 'Webshot rate limit exceeded.',
                retryAfter: $this->headerInt($headers, 'Retry-After', 60),
                resetAt:    $this->jsonInt($json, 'reset_at'),
                limit:      $this->jsonInt($json, 'limit', 5) ?? 5,
                available:  $this->jsonInt($json, 'available', 0) ?? 0,
                contact:    is_string($json['contact'] ?? null) ? $json['contact'] : 'sales@tuxxin.com',
            );
        }
        if ($status >= 400) {
            $json = $this->safeJson($body);
            throw new ApiException(
                message:  is_string($json['error'] ?? null) ? $json['error'] : "Webshot API HTTP {$status}",
                status:   $status,
                docsUrl:  is_string($json['docs'] ?? null) ? $json['docs'] : null,
            );
        }

        return new CaptureResult(
            bytes:         $body,
            contentType:   $this->headerStr($headers, 'Content-Type', 'application/octet-stream'),
            source:        $this->headerStr($headers, 'X-Webshot-Source'),
            mode:          $this->headerStr($headers, 'X-Webshot-Mode'),
            rateLimit:     $this->headerInt($headers, 'X-RateLimit-Limit'),
            rateRemaining: $this->headerInt($headers, 'X-RateLimit-Remaining'),
            rateResetAt:   $this->headerInt($headers, 'X-RateLimit-Reset'),
        );
    }

    /** Get the caller's current rate-limit bucket without spending a credit. */
    public function throttleStatus(): ThrottleStatus
    {
        [$status, $body] = $this->executeHttp('GET', $this->baseUrl . '/throttle-status', null, [
            'Accept: application/json',
        ]);
        if ($status >= 400) {
            $json = $this->safeJson($body);
            throw new ApiException(
                message: is_string($json['error'] ?? null) ? $json['error'] : "Webshot API HTTP {$status}",
                status:  $status,
            );
        }
        $json = $this->safeJson($body);
        return new ThrottleStatus(
            available:      $this->jsonInt($json, 'available')         ?? 0,
            used:           $this->jsonInt($json, 'used')              ?? 0,
            limit:          $this->jsonInt($json, 'limit')             ?? 5,
            ratePerMinute:  is_numeric($json['rate_per_minute'] ?? null) ? (float) $json['rate_per_minute'] : 0.0,
            nextReleaseAt:  $this->jsonInt($json, 'next_release_at'),
        );
    }

    /**
     * Run a cURL request. Subclass and override for custom transports.
     *
     * @param string                $method   'GET' | 'POST' | etc.
     * @param string                $url      Full URL.
     * @param string|null           $body     POST body, or null for GET.
     * @param array<string>         $headers  ['Header: value', ...]
     * @return array{0:int, 1:string, 2:array<string,string>}
     *         [status_code, raw_body, lower-keyed_headers]
     */
    protected function executeHttp(string $method, string $url, ?string $body, array $headers): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new WebshotException('curl_init failed.');
        }

        $allHeaders = array_merge($headers, ['User-Agent: ' . $this->userAgent]);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => $this->timeoutS,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => $allHeaders,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new WebshotException("Webshot network error: {$err}");
        }
        $status     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headerBlob = substr($raw, 0, $headerSize);
        $bodyOnly   = substr($raw, $headerSize);

        return [$status, $bodyOnly, $this->parseHeaders($headerBlob)];
    }

    /**
     * @param string $blob Header block from cURL (one header per line).
     * @return array<string,string> lower-keyed header → value (last-write-wins).
     */
    private function parseHeaders(string $blob): array
    {
        $out = [];
        foreach (preg_split('/\r?\n/', $blob) ?: [] as $line) {
            $pos = strpos($line, ':');
            if ($pos === false) continue;
            $k = strtolower(trim(substr($line, 0, $pos)));
            $v = trim(substr($line, $pos + 1));
            $out[$k] = $v;
        }
        return $out;
    }

    /** @param array<string,string> $headers */
    private function headerStr(array $headers, string $name, ?string $default = null): ?string
    {
        $v = $headers[strtolower($name)] ?? null;
        return $v === null ? $default : $v;
    }

    /** @param array<string,string> $headers */
    private function headerInt(array $headers, string $name, ?int $default = null): ?int
    {
        $v = $headers[strtolower($name)] ?? null;
        if ($v === null || !is_numeric($v)) return $default;
        return (int) $v;
    }

    /** @param array<string,mixed>|null $json */
    private function jsonInt(?array $json, string $key, ?int $default = null): ?int
    {
        if (!is_array($json) || !isset($json[$key]) || !is_numeric($json[$key])) return $default;
        return (int) $json[$key];
    }

    /** @return array<string,mixed>|null */
    private function safeJson(string $body): ?array
    {
        if ($body === '') return null;
        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        return is_array($data) ? $data : null;
    }
}
