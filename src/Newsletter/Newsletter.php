<?php

declare(strict_types=1);

namespace Hirtz\Forms\Newsletter;

use Closure;
use Hirtz\Forms\Helpers\Param;
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
        $apiKey = Param::getString('brevoApiKey');
        $listId = Param::getString('brevoListId');
        $templateId = Yii::$app->params['brevoTemplateId'] ?? null;

        if ($apiKey !== null && $listId !== null && $templateId) {
            return Yii::createObject([
                'class' => Brevo::class,
                'apiKey' => $apiKey,
                'listIds' => array_map(intval(...), explode(',', $listId)),
                'templateId' => is_array($templateId) ? array_map(intval(...), $templateId) : (int)$templateId,
            ]);
        }

        $apiKey = Param::getString('mailchimpApiKey');
        $listId = Param::getString('mailchimpListId') ?? $this->getMailchimpListIds();

        if ($apiKey !== null && $listId) {
            return Yii::createObject([
                'class' => Mailchimp::class,
                'apiKey' => $apiKey,
                'listId' => $listId,
                'server' => Param::getString('mailchimpServer'),
            ]);
        }

        if ($url = Param::getString('newsletterWebhookUrl')) {
            return Yii::createObject([
                'class' => Webhook::class,
                'url' => $url,
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
            if (is_string($name) && str_starts_with($name, 'mailchimpListId_') && ($listId = Param::getString($name))) {
                $listIds[substr($name, 16)] = $listId;
            }
        }

        return $listIds;
    }
}
