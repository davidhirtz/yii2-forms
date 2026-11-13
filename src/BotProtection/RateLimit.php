<?php

declare(strict_types=1);

namespace Hirtz\Forms\BotProtection;

use Hirtz\Skeleton\Caching\CacheCounter;
use Hirtz\Skeleton\Consent\Service;
use Hirtz\Skeleton\Web\Request;
use Override;

/**
 * Counts every submit that reaches it, per IP address and across all forms.
 */
class RateLimit implements ProtectionInterface
{
    public function __construct(
        public int $limit = 10,
        public int $duration = 3600,
    ) {
    }

    #[Override]
    public function verify(Request $request): bool
    {
        $key = [self::class, $this->getOrigin((string)$request->getUserIP())];
        return CacheCounter::increment($key, $this->duration) <= $this->limit;
    }

    #[Override]
    public function render(): string
    {
        return '';
    }

    #[Override]
    public function getConsentService(): ?Service
    {
        return null;
    }

    /**
     * An IPv6 address is counted by its /64: a single host is given the whole network and can rotate through it.
     */
    private function getOrigin(string $ipAddress): string
    {
        $packed = @inet_pton($ipAddress);

        return is_string($packed) && strlen($packed) === 16
            ? bin2hex(substr($packed, 0, 8)) . '/64'
            : $ipAddress;
    }
}
