<?php

declare(strict_types=1);

namespace Hirtz\Forms\BotProtection;

use Hirtz\Skeleton\Consent\Service;
use Hirtz\Skeleton\Helpers\Html;
use Hirtz\Skeleton\Web\Application;
use Hirtz\Skeleton\Web\Request;
use Override;
use Yii;
use yii\filters\PageCache;
use yii\widgets\FragmentCache;

/**
 * On a page-cached form the render time is the cache's: `minSeconds` only holds back a bot that renders the form
 * itself, and the token lives as long as the caches around the form plus `maxSeconds`.
 */
class Honeypot implements ProtectionInterface
{
    public function __construct(
        public string $fieldName = 'website',
        public string $timeName = 'rendered',
        public int $minSeconds = 3,
        public ?int $maxSeconds = 172800,
    ) {
    }

    #[Override]
    public function verify(Request $request): bool
    {
        if ($request->post($this->fieldName, '') !== '') {
            return false;
        }

        $token = $request->post($this->timeName);
        $token = is_string($token) ? Yii::$app->getSecurity()->validateData($token, $this->getKey($request)) : false;

        // A token without a maximum was rendered before there was one
        if ($token === false || !preg_match('/^(\d+)(?::(\d+))?\z/', $token, $matches)) {
            return false;
        }

        $age = time() - (int)$matches[1];
        $maxAge = (int)($matches[2] ?? 0);

        return $age >= $this->minSeconds && ($maxAge === 0 || $age <= $maxAge);
    }

    #[Override]
    public function render(): string
    {
        $request = Request::current();
        $token = $request ? Yii::$app->getSecurity()->hashData(time() . ':' . $this->getMaxAge(), $this->getKey($request)) : '';

        return '<div hidden aria-hidden="true">'
            . Html::input('text', $this->fieldName, '', ['tabindex' => '-1', 'autocomplete' => 'off'])
            . Html::hiddenInput($this->timeName, $token)
            . '</div>';
    }

    #[Override]
    public function getConsentService(): ?Service
    {
        return null;
    }

    /**
     * `0` for no limit.
     */
    protected function getMaxAge(): int
    {
        if (!$this->maxSeconds) {
            return 0;
        }

        $maxAge = $this->maxSeconds;

        foreach (Application::current()->getView()->getDynamicContents() as $cache) {
            if ($cache instanceof PageCache || $cache instanceof FragmentCache) {
                if ($cache->duration <= 0) {
                    return 0;
                }

                $maxAge += $cache->duration;
            }
        }

        return $maxAge;
    }

    private function getKey(Request $request): string
    {
        return $request->cookieValidationKey . self::class;
    }
}
