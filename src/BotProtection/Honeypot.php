<?php

declare(strict_types=1);

namespace Hirtz\Forms\BotProtection;

use Hirtz\Skeleton\Consent\Service;
use Hirtz\Skeleton\Helpers\Html;
use Hirtz\Skeleton\Web\Request;
use Yii;

/**
 * On a page-cached form the render time is the cache's: `minSeconds` only holds back a bot that renders the form
 * itself, and `maxSeconds` must outlast the page cache.
 */
class Honeypot implements ProtectionInterface
{
    public function __construct(
        public string $fieldName = 'website',
        public string $timeName = 'rendered',
        public int $minSeconds = 3,
        public ?int $maxSeconds = null,
    ) {
    }

    public function verify(Request $request): bool
    {
        if ($request->post($this->fieldName, '') !== '') {
            return false;
        }

        $time = $request->post($this->timeName);
        $time = is_string($time) ? Yii::$app->getSecurity()->validateData($time, $this->getKey($request)) : false;

        if ($time === false || !ctype_digit($time)) {
            return false;
        }

        $age = time() - (int)$time;
        return $age >= $this->minSeconds && ($this->maxSeconds === null || $age <= $this->maxSeconds);
    }

    public function render(): string
    {
        $request = Request::current();
        $time = $request ? Yii::$app->getSecurity()->hashData((string)time(), $this->getKey($request)) : '';

        return '<div hidden aria-hidden="true">'
            . Html::input('text', $this->fieldName, '', ['tabindex' => '-1', 'autocomplete' => 'off'])
            . Html::hiddenInput($this->timeName, $time)
            . '</div>';
    }

    public function getConsentService(): ?Service
    {
        return null;
    }

    private function getKey(Request $request): string
    {
        return $request->cookieValidationKey . self::class;
    }
}
