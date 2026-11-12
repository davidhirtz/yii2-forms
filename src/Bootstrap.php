<?php

declare(strict_types=1);

namespace Hirtz\Forms;

use Hirtz\Forms\BotProtection\BotProtection;
use Hirtz\Forms\BotProtection\Honeypot;
use Hirtz\Forms\BotProtection\ProtectionInterface;
use Hirtz\Forms\BotProtection\Turnstile;
use Hirtz\Forms\Newsletter\Brevo;
use Hirtz\Forms\Newsletter\Newsletter;
use Hirtz\Forms\Newsletter\ProviderInterface;
use Hirtz\Forms\Newsletter\Webhook;
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
                'newsletter' => [
                    'class' => Newsletter::class,
                    'provider' => static fn (): ?ProviderInterface => self::createNewsletterProvider(),
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
                'newsletterWebhookUrl',
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
        $protections = [new Honeypot()];

        $siteKey = self::getParam('cfTurnstileSiteKey');
        $secretKey = self::getParam('cfTurnstileSecretKey');

        if ($siteKey !== null && $secretKey !== null) {
            $protections[] = new Turnstile($siteKey, $secretKey);
        }

        return $protections;
    }

    protected static function createNewsletterProvider(): ?ProviderInterface
    {
        $apiKey = self::getParam('brevoApiKey');
        $listId = self::getParam('brevoListId');
        $templateId = Yii::$app->params['brevoTemplateId'] ?? null;

        if ($apiKey !== null && $listId !== null && $templateId) {
            return Yii::createObject([
                'class' => Brevo::class,
                'apiKey' => $apiKey,
                'listIds' => array_map(intval(...), explode(',', $listId)),
                'templateId' => is_array($templateId) ? array_map(intval(...), $templateId) : (int)$templateId,
            ]);
        }

        if ($url = self::getParam('newsletterWebhookUrl')) {
            return Yii::createObject([
                'class' => Webhook::class,
                'url' => $url,
            ]);
        }

        return null;
    }

    private static function getParam(string $name): ?string
    {
        $value = Yii::$app->params[$name] ?? null;
        return is_int($value) || (is_string($value) && $value !== '') ? (string)$value : null;
    }
}
