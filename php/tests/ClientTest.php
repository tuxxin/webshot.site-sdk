<?php
declare(strict_types=1);

namespace Tuxxin\Webshot\Tests;

use PHPUnit\Framework\TestCase;
use Tuxxin\Webshot\Client;
use Tuxxin\Webshot\Exception\ApiException;
use Tuxxin\Webshot\Exception\ThrottledException;
use Tuxxin\Webshot\Exception\WebshotException;

/**
 * Unit tests using a Client subclass that overrides executeHttp() — no real
 * HTTP traffic. Run with: vendor/bin/phpunit
 */
final class ClientTest extends TestCase
{
    public function testCaptureSuccessReturnsBytesAndMetadata(): void
    {
        $client = new MockClient([
            'status'  => 200,
            'body'    => "\x89PNG\r\n\x1a\nfake-bytes",
            'headers' => [
                'content-type'          => 'image/png',
                'x-webshot-source'      => 'example.com',
                'x-webshot-mode'        => 'desktop_full',
                'x-ratelimit-limit'     => '5',
                'x-ratelimit-remaining' => '4',
                'x-ratelimit-reset'     => '1234567890',
            ],
        ]);

        $shot = $client->capture('https://example.com', format: 'png');

        $this->assertStringStartsWith("\x89PNG", $shot->bytes);
        $this->assertSame('image/png', $shot->contentType);
        $this->assertSame('example.com', $shot->source);
        $this->assertSame('desktop_full', $shot->mode);
        $this->assertSame(5, $shot->rateLimit);
        $this->assertSame(4, $shot->rateRemaining);
        $this->assertSame(1234567890, $shot->rateResetAt);
    }

    public function test429RaisesThrottledExceptionWithRetryInfo(): void
    {
        $client = new MockClient([
            'status'  => 429,
            'body'    => json_encode([
                'error'       => 'Rate limit exceeded.',
                'retry_after' => 47,
                'reset_at'    => 1700000000,
                'limit'       => 5,
                'available'   => 0,
                'contact'     => 'sales@tuxxin.com',
            ]),
            'headers' => ['retry-after' => '47'],
        ]);

        try {
            $client->capture('https://example.com');
            $this->fail('expected ThrottledException');
        } catch (ThrottledException $e) {
            $this->assertSame(47, $e->getRetryAfter());
            $this->assertSame(1700000000, $e->getResetAt());
            $this->assertSame(5, $e->getLimit());
            $this->assertSame(0, $e->getAvailable());
            $this->assertSame('sales@tuxxin.com', $e->getContact());
        }
    }

    public function testNon429ErrorRaisesApiException(): void
    {
        $client = new MockClient([
            'status'  => 400,
            'body'    => json_encode([
                'error' => 'Missing required parameter: url',
                'docs'  => 'https://webshot.site/developers',
            ]),
            'headers' => [],
        ]);

        try {
            $client->capture('https://example.com');
            $this->fail('expected ApiException');
        } catch (ApiException $e) {
            $this->assertSame(400, $e->getStatus());
            $this->assertStringContainsString('Missing required parameter', $e->getMessage());
            $this->assertSame('https://webshot.site/developers', $e->getDocsUrl());
        }
    }

    public function testCaptureValidatesArgsLocally(): void
    {
        $client = new MockClient(['status' => 200, 'body' => '', 'headers' => []]);

        $this->expectException(WebshotException::class);
        /** @noinspection PhpUnhandledExceptionInspection */
        $client->capture('https://example.com', format: 'bmp');
    }

    public function testThrottleStatusReturnsObject(): void
    {
        $client = new MockClient([
            'status'  => 200,
            'body'    => json_encode([
                'available'       => 3,
                'used'            => 2,
                'limit'           => 5,
                'rate_per_minute' => 0.333,
                'next_release_at' => 1700000600,
            ]),
            'headers' => ['content-type' => 'application/json'],
        ]);

        $s = $client->throttleStatus();
        $this->assertSame(3, $s->available);
        $this->assertSame(2, $s->used);
        $this->assertSame(5, $s->limit);
        $this->assertEqualsWithDelta(0.333, $s->ratePerMinute, 0.001);
        $this->assertSame(1700000600, $s->nextReleaseAt);
    }
}

/** Tiny test double that returns a canned HTTP response instead of doing real I/O. */
final class MockClient extends Client
{
    /** @param array{status:int, body:string, headers:array<string,string>} $canned */
    public function __construct(private readonly array $canned)
    {
        parent::__construct();
    }

    protected function executeHttp(string $method, string $url, ?string $body, array $headers): array
    {
        return [$this->canned['status'], $this->canned['body'], $this->canned['headers']];
    }
}
