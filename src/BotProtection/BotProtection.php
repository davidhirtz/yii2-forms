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
     * A closure, so that a project's list replaces the bundle's instead of being appended to it.
     *
     * @var Closure(): list<ProtectionInterface>|list<ProtectionInterface>
     */
    public Closure|array $protections = [];

    /**
     * @var list<ProtectionInterface>|null
     */
    private ?array $resolvedProtections = null;

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
        return $this->resolvedProtections ??= $this->protections instanceof Closure
            ? ($this->protections)()
            : $this->protections;
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
