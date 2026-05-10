<?php
declare(strict_types=1);

namespace Tuxxin\Webshot;

/** Return value of `Client::throttleStatus()`. Snapshot of the caller's rate-limit bucket. */
final class ThrottleStatus
{
    public function __construct(
        public readonly int $available,
        public readonly int $used,
        public readonly int $limit,
        public readonly float $ratePerMinute,
        public readonly ?int $nextReleaseAt,
    ) {
    }
}
