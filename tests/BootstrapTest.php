<?php

declare(strict_types=1);

namespace Hirtz\Forms\Tests;

use Hirtz\Forms\BotProtection\BotProtection;
use Hirtz\Forms\BotProtection\Honeypot;
use Hirtz\Forms\BotProtection\ProtectionInterface;
use Hirtz\Forms\BotProtection\Turnstile;
use Hirtz\Forms\Newsletter\Brevo;
use Hirtz\Forms\Newsletter\Mailchimp;
use Hirtz\Forms\Newsletter\Newsletter;
use Hirtz\Forms\Newsletter\Webhook;
use Hirtz\Skeleton\Consent\ConsentManager;
use Hirtz\Skeleton\Consent\Service;
use Hirtz\Skeleton\Test\TestCase;
use Yii;

class BootstrapTest extends TestCase
{
    public function testTheSecretsAreSensitiveParams(): void
    {
        self::assertContains('cfTurnstileSecretKey', Yii::$app->sensitiveParams);
        self::assertContains('brevoApiKey', Yii::$app->sensitiveParams);
        self::assertContains('mailchimpApiKey', Yii::$app->sensitiveParams);
        self::assertContains('newsletterWebhookUrl', Yii::$app->sensitiveParams);
    }

    public function testWithoutParamsTheNewsletterHasNoProvider(): void
    {
        self::assertNull(Newsletter::current()->getProvider());
    }

    public function testTheBrevoParamsWireBrevoBeforeTheWebhook(): void
    {
        $this->config['params'] = [
            ...$this->config['params'] ?? [],
            'brevoApiKey' => 'api-key',
            'brevoListId' => '3,5',
            'brevoTemplateId' => 7,
            'newsletterWebhookUrl' => 'https://flow.example.com/webhook',
        ];

        $this->config['container']['definitions'][Brevo::class] = ['redirectUrl' => ['/subscribed']];
        $this->reloadApplication();

        $provider = Newsletter::current()->getProvider();
        self::assertInstanceOf(Brevo::class, $provider);
        self::assertSame([3, 5], $provider->listIds);
        self::assertSame(7, $provider->templateId);
        self::assertSame(['/subscribed'], $provider->redirectUrl);
    }

    public function testTheMailchimpParamsWireMailchimpWithAListPerLanguage(): void
    {
        $this->config['params'] = [
            ...$this->config['params'] ?? [],
            'mailchimpApiKey' => 'key-us21',
            'mailchimpListId_de' => 'list-de',
            'mailchimpListId_en' => 'list-en',
            'newsletterWebhookUrl' => 'https://flow.example.com/webhook',
        ];

        $this->reloadApplication();

        $provider = Newsletter::current()->getProvider();
        self::assertInstanceOf(Mailchimp::class, $provider);
        self::assertSame(['de' => 'list-de', 'en' => 'list-en'], $provider->listId);
        self::assertSame('us21', $provider->server);
    }

    public function testTheWebhookParamWiresTheWebhook(): void
    {
        $this->config['params'] = [
            ...$this->config['params'] ?? [],
            'newsletterWebhookUrl' => 'https://flow.example.com/webhook',
        ];

        $this->reloadApplication();

        self::assertInstanceOf(Webhook::class, Newsletter::current()->getProvider());
    }

    public function testWithoutParamsOnlyTheHoneypotIsWired(): void
    {
        self::assertSame([Honeypot::class], $this->getProtectionClasses());
        self::assertSame(['Cookie settings'], $this->getRequiredServiceNames());
    }

    public function testTheTurnstileParamsWireTurnstileAndItsConsentService(): void
    {
        $this->config['params'] = [
            ...$this->config['params'] ?? [],
            'cfTurnstileSiteKey' => 'site-key',
            'cfTurnstileSecretKey' => 'secret-key',
        ];

        $this->reloadApplication();

        self::assertSame([Honeypot::class, Turnstile::class], $this->getProtectionClasses());
        self::assertSame(['Cookie settings', 'Cloudflare Turnstile'], $this->getRequiredServiceNames());
    }

    public function testAProjectReplacesTheProtections(): void
    {
        $this->config['params'] = [
            ...$this->config['params'] ?? [],
            'cfTurnstileSiteKey' => 'site-key',
            'cfTurnstileSecretKey' => 'secret-key',
        ];

        $this->config['components']['botProtection']['protections'] = fn (): array => [new Honeypot(minSeconds: 0)];
        $this->reloadApplication();

        self::assertSame([Honeypot::class], $this->getProtectionClasses());
    }

    /**
     * @return list<class-string>
     */
    private function getProtectionClasses(): array
    {
        return array_map(
            fn (ProtectionInterface $protection): string => $protection::class,
            BotProtection::current()->getProtections(),
        );
    }

    /**
     * @return list<string>
     */
    private function getRequiredServiceNames(): array
    {
        return array_map(
            fn (Service $service): string => $service->getName(),
            ConsentManager::current()->getCategories()[0]->getServices(),
        );
    }
}
