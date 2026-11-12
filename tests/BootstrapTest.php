<?php

declare(strict_types=1);

namespace Hirtz\Forms\Tests;

use Hirtz\Forms\BotProtection\BotProtection;
use Hirtz\Forms\BotProtection\Honeypot;
use Hirtz\Forms\BotProtection\ProtectionInterface;
use Hirtz\Forms\BotProtection\Turnstile;
use Hirtz\Skeleton\Consent\ConsentManager;
use Hirtz\Skeleton\Consent\Service;
use Hirtz\Skeleton\Test\TestCase;
use Yii;

class BootstrapTest extends TestCase
{
    public function testTheSecretKeyIsASensitiveParam(): void
    {
        self::assertContains('cfTurnstileSecretKey', Yii::$app->sensitiveParams);
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
