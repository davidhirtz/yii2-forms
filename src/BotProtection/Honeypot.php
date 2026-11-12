<?php

declare(strict_types=1);

namespace Hirtz\Forms\BotProtection;

use Hirtz\Skeleton\Consent\Service;
use Hirtz\Skeleton\Helpers\Html;
use Hirtz\Skeleton\Web\Request;
use Override;
use Yii;

/**
 * A field that must stay empty and a signed render time a submit must not follow too closely. On a page-cached form
 * the time is the cache's, so the delay only holds back a bot that renders the form itself.
 */
class Honeypot implements ProtectionInterface
{
    public function __construct(
        public string $fieldName = 'website',
        public string $timeName = 'rendered',
        public int $minSeconds = 3,
    ) {
    }

    #[Override]
    public function verify(Request $request): bool
    {
        if ($request->post($this->fieldName, '') !== '') {
            return false;
        }

        $time = $request->post($this->timeName);
        $time = is_string($time) ? Yii::$app->getSecurity()->validateData($time, $this->getKey($request)) : false;

        return $time !== false && ctype_digit($time) && time() - (int)$time >= $this->minSeconds;
    }

    #[Override]
    public function render(): string
    {
        $request = Request::current();
        $time = $request ? Yii::$app->getSecurity()->hashData((string)time(), $this->getKey($request)) : '';

        return '<div hidden aria-hidden="true">'
            . Html::input('text', $this->fieldName, '', ['tabindex' => '-1', 'autocomplete' => 'off'])
            . Html::hiddenInput($this->timeName, $time)
            . '</div>';
    }

    #[Override]
    public function getConsentService(): ?Service
    {
        return null;
    }

    private function getKey(Request $request): string
    {
        return $request->cookieValidationKey . self::class;
    }
}
