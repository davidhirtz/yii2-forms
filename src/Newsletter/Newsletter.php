<?php

declare(strict_types=1);

namespace Hirtz\Forms\Newsletter;

use Closure;
use Yii;
use yii\base\Component;

class Newsletter extends Component
{
    /**
     * Built from params when `null`; `false` has none.
     *
     * @var Closure(): (ProviderInterface|null)|ProviderInterface|false|null
     */
    public Closure|ProviderInterface|false|null $provider = null;

    public static function current(): self
    {
        /** @var self $component */
        $component = Yii::$app->get('newsletter');
        return $component;
    }

    public function getProvider(): ?ProviderInterface
    {
        if ($this->provider instanceof Closure) {
            $this->provider = ($this->provider)() ?? false;
        }

        $this->provider ??= $this->getDefaultProvider() ?? false;

        return $this->provider ?: null;
    }

    protected function getDefaultProvider(): ?ProviderInterface
    {
        $params = Yii::$app->params;

        $apiKey = $params['brevoApiKey'] ?? null;
        $listId = $params['brevoListId'] ?? null;
        $templateId = $params['brevoTemplateId'] ?? null;

        if ($apiKey && $listId && $templateId) {
            return Yii::createObject([
                'class' => Brevo::class,
                'apiKey' => (string)$apiKey,
                'listIds' => array_map(intval(...), explode(',', (string)$listId)),
                'templateId' => is_array($templateId) ? array_map(intval(...), $templateId) : (int)$templateId,
            ]);
        }

        $apiKey = $params['mailchimpApiKey'] ?? null;
        $listId = ($params['mailchimpListId'] ?? null) ?: $this->getMailchimpListIds();

        if ($apiKey && $listId) {
            return Yii::createObject([
                'class' => Mailchimp::class,
                'apiKey' => (string)$apiKey,
                'listId' => is_array($listId) ? $listId : (string)$listId,
                'server' => ($params['mailchimpServer'] ?? null) ?: null,
            ]);
        }

        if ($url = $params['newsletterWebhookUrl'] ?? null) {
            return Yii::createObject([
                'class' => Webhook::class,
                'url' => (string)$url,
            ]);
        }

        return null;
    }

    /**
     * yii2-config stores flat strings, hence `mailchimpListId_de` rather than an array.
     *
     * @return array<string, string>
     */
    private function getMailchimpListIds(): array
    {
        $listIds = [];

        foreach (array_keys(Yii::$app->params) as $name) {
            if (is_string($name) && str_starts_with($name, 'mailchimpListId_') && ($listId = Yii::$app->params[$name])) {
                $listIds[substr($name, 16)] = (string)$listId;
            }
        }

        return $listIds;
    }
}
