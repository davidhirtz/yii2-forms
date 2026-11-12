<?php

declare(strict_types=1);

namespace Hirtz\Forms;

use Hirtz\Forms\BotProtection\BotProtection;
use Hirtz\Forms\BotProtection\Honeypot;
use Hirtz\Forms\BotProtection\ProtectionInterface;
use Hirtz\Forms\BotProtection\Turnstile;
use Hirtz\Skeleton\Base\ConfigBootstrapInterface;
use Hirtz\Skeleton\Consent\Categories\RequiredCategory;
use Hirtz\Skeleton\Consent\Service;
use Override;
use Yii;
use yii\i18n\PhpMessageSource;

class Bootstrap implements ConfigBootstrapInterface
{
    #[Override]
    public static function getDefaultConfig(): array
    {
        return [
            'components' => [
                'botProtection' => [
                    'class' => BotProtection::class,
                    'protections' => static fn (): array => self::createProtections(),
                ],
                'consent' => [
                    'services' => [
                        RequiredCategory::ID => [
                            static fn (): ?Service => BotProtection::current()->getConsentService(),
                        ],
                    ],
                ],
                'i18n' => [
                    'translations' => [
                        'forms' => [
                            'class' => PhpMessageSource::class,
                            'basePath' => '@forms/../messages',
                            'forceTranslation' => true,
                        ],
                    ],
                ],
            ],
            'sensitiveParams' => [
                'cfTurnstileSecretKey',
            ],
        ];
    }

    #[Override]
    public function bootstrap($app): void
    {
        Yii::setAlias('@forms', __DIR__);
    }

    /**
     * @return list<ProtectionInterface>
     */
    protected static function createProtections(): array
    {
        $params = Yii::$app->params;
        $protections = [new Honeypot()];

        $siteKey = $params['cfTurnstileSiteKey'] ?? null;
        $secretKey = $params['cfTurnstileSecretKey'] ?? null;

        if (is_string($siteKey) && $siteKey !== '' && is_string($secretKey) && $secretKey !== '') {
            $protections[] = new Turnstile($siteKey, $secretKey);
        }

        return $protections;
    }
}
