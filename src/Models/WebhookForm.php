<?php

declare(strict_types=1);

namespace Hirtz\Forms\Models;

use Hirtz\Forms\Webhooks\Webhook;
use Hirtz\Forms\Webhooks\WebhookException;
use Hirtz\Skeleton\Web\Request;
use Override;
use Yii;
use yii\base\InvalidConfigException;

abstract class WebhookForm extends Form
{
    public ?string $webhookUrl = null;

    #[Override]
    public function init(): void
    {
        $this->webhookUrl ??= (Yii::$app->params['formWebhookUrl'] ?? null) ?: null;
        parent::init();
    }

    /**
     * The webhook's URL is a property, never an attribute a post could load or the payload list.
     */
    #[Override]
    public function attributes(): array
    {
        return array_values(array_diff(parent::attributes(), ['webhookUrl']));
    }

    #[Override]
    public function submit(): bool
    {
        if (!$this->webhookUrl) {
            throw new InvalidConfigException(static::class . ' needs "webhookUrl".');
        }

        try {
            $this->getWebhook()->post($this->getWebhookData());
        } catch (WebhookException $exception) {
            Yii::error($exception->getMessage(), __METHOD__);
            $this->addError('', Yii::t('forms', 'FORM_SEND_ERROR'));
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getWebhookData(): array
    {
        $request = Request::current();

        return [
            ...$this->getAttributes(),
            'language' => Yii::$app->language,
            'ip' => $request?->getUserIP(),
            'url' => $request?->getReferrer(),
        ];
    }

    protected function getWebhook(): Webhook
    {
        return Yii::createObject([
            'class' => Webhook::class,
            'url' => $this->webhookUrl,
        ]);
    }
}
