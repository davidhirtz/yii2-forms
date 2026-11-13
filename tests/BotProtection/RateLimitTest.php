<?php

declare(strict_types=1);

namespace Hirtz\Forms\Tests\BotProtection;

use Hirtz\Forms\BotProtection\RateLimit;
use Hirtz\Skeleton\Test\TestCase;
use Hirtz\Skeleton\Web\Request;

class RateLimitTest extends TestCase
{
    public function testSubmitsBeyondTheLimitFail(): void
    {
        $rateLimit = new RateLimit(limit: 2);
        $ip = '192.0.2.' . random_int(1, 254);

        self::assertTrue($rateLimit->verify($this->createRequest($ip)));
        self::assertTrue($rateLimit->verify($this->createRequest($ip)));
        self::assertFalse($rateLimit->verify($this->createRequest($ip)));
        self::assertTrue($rateLimit->verify($this->createRequest('198.51.100.' . random_int(1, 254))));
    }

    public function testAnIpv6NetworkCountsAsOne(): void
    {
        $rateLimit = new RateLimit(limit: 1);
        $network = sprintf('2001:db8:%x:%x', random_int(0, 0xffff), random_int(0, 0xffff));

        self::assertTrue($rateLimit->verify($this->createRequest("$network::1")));
        self::assertFalse($rateLimit->verify($this->createRequest("$network::2")));
    }

    private function createRequest(string $ip): Request
    {
        $_SERVER['REMOTE_ADDR'] = $ip;
        return new Request(['cookieValidationKey' => 'test']);
    }
}
