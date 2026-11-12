<?php

declare(strict_types=1);

namespace Hirtz\Forms\Newsletter;

readonly class Subscription
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $email,
        public string $language,
        public ?string $ip = null,
        public ?string $pageUrl = null,
        public array $attributes = [],
    ) {
    }
}
