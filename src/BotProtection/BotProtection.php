<?php

declare(strict_types=1);

namespace Hirtz\Forms\BotProtection;

use Closure;
use Hirtz\Skeleton\Consent\Service;
use Hirtz\Skeleton\Web\Request;
use Yii;
use yii\base\Component;

class BotProtection extends Component
{
    /**
     * Built from params when `null`.
     *
     * @var Closure(): list<ProtectionInterface>|list<ProtectionInterface>|null
     */
    public Closure|array|null $protections = null;

    public static function current(): self
    {
        /** @var self $component */
        $component = Yii::$app->get('botProtection');
        return $component;
    }

    /**
     * @return list<ProtectionInterface>
     */
    public function getProtections(): array
    {
        if ($this->protections instanceof Closure) {
            $this->protections = ($this->protections)();
        }

        return $this->protections ??= $this->getDefaultProtections();
    }

    /**
     * @return list<ProtectionInterface>
     */
    protected function getDefaultProtections(): array
    {
        $protections = [
            Yii::createObject(Honeypot::class),
            Yii::createObject(RateLimit::class),
        ];

        $siteKey = Yii::$app->params['cfTurnstileSiteKey'] ?? null;
        $secretKey = Yii::$app->params['cfTurnstileSecretKey'] ?? null;

        if ($siteKey && $secretKey) {
            $protections[] = Yii::createObject(Turnstile::class, [(string)$siteKey, (string)$secretKey]);
        }

        return $protections;
    }

    public function verify(Request $request): bool
    {
        foreach ($this->getProtections() as $protection) {
            if (!$protection->verify($request)) {
                return false;
            }
        }

        return true;
    }

    public function render(): string
    {
        return implode('', array_map(
            fn (ProtectionInterface $protection): string => $protection->render(),
            $this->getProtections(),
        ));
    }

    public function getConsentService(): ?Service
    {
        foreach ($this->getProtections() as $protection) {
            if ($service = $protection->getConsentService()) {
                return $service;
            }
        }

        return null;
    }
}
