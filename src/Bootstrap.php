<?php

declare(strict_types=1);

namespace Hirtz\Forms;

use Hirtz\Forms\BotProtection\BotProtection;
use Hirtz\Forms\Newsletter\Newsletter;
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
                ],
                'consent' => [
                    'services' => [
                        RequiredCategory::ID => [
                            static fn (): ?Service => BotProtection::current()->getConsentService(),
                        ],
                    ],
                ],
                'newsletter' => [
                    'class' => Newsletter::class,
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
                'brevoApiKey',
                'cfTurnstileSecretKey',
                'formWebhookUrl',
                'mailchimpApiKey',
                'newsletterWebhookUrl',
            ],
        ];
    }

    #[Override]
    public function bootstrap($app): void
    {
        Yii::setAlias('@forms', __DIR__);
    }
}
