<?php

declare(strict_types=1);

namespace Hirtz\Forms\Newsletter;

use Hirtz\Forms\Webhooks\Webhook as BaseWebhook;
use Hirtz\Forms\Webhooks\WebhookException;
use Override;

class Webhook extends BaseWebhook implements ProviderInterface
{
    #[Override]
    public function subscribe(Subscription $subscription): void
    {
        try {
            $this->post([
                ...$subscription->attributes,
                'email' => $subscription->email,
                'language' => $subscription->language,
                'ip' => $subscription->ip,
                'url' => $subscription->pageUrl,
            ]);
        } catch (WebhookException $exception) {
            throw new NewsletterException($exception->getMessage());
        }
    }
}
