<?php

declare(strict_types=1);

namespace Hirtz\Forms\Newsletter;

interface ProviderInterface
{
    /**
     * @throws AlreadySubscribedException
     * @throws NewsletterException
     */
    public function subscribe(Subscription $subscription): void;
}
