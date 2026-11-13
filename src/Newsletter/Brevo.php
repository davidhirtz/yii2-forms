<?php

declare(strict_types=1);

namespace Hirtz\Forms\Newsletter;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Override;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;
use yii\helpers\Url;

class Brevo extends BaseObject implements ProviderInterface
{
    use LanguageMapTrait;

    private const string URL = 'https://api.brevo.com/v3/contacts/doubleOptinConfirmation';

    public string $apiKey = '';

    /**
     * @var list<int>
     */
    public array $listIds = [];

    /**
     * @var int|array<string, int>
     */
    public int|array $templateId = 0;

    /**
     * @var array<int|string, mixed>|string
     */
    public array|string $redirectUrl = ['/'];

    public ?ClientInterface $client = null;

    #[Override]
    public function init(): void
    {
        if ($this->apiKey === '' || !$this->listIds || !$this->templateId) {
            throw new InvalidConfigException(static::class . ' needs "apiKey", "listIds" and "templateId".');
        }

        parent::init();
    }

    #[Override]
    public function subscribe(Subscription $subscription): void
    {
        try {
            $this->getClient()->request('POST', self::URL, [
                'headers' => [
                    'api-key' => $this->apiKey,
                    'accept' => 'application/json',
                ],
                'json' => array_filter([
                    'email' => $subscription->email,
                    'attributes' => $subscription->attributes,
                    'includeListIds' => $this->listIds,
                    'templateId' => $this->getTemplateId($subscription->language),
                    'redirectionUrl' => Url::to($this->redirectUrl, true),
                ]),
            ]);
        } catch (RequestException $exception) {
            $data = json_decode((string)$exception->getResponse()?->getBody(), true);
            $code = is_array($data) && is_string($data['code'] ?? null) ? $data['code'] : '';
            $message = is_array($data) && is_string($data['message'] ?? null) ? $data['message'] : '';

            if ($code === 'duplicate_parameter') {
                throw new AlreadySubscribedException($message, previous: $exception);
            }

            $status = $exception->getResponse()?->getStatusCode() ?? 0;
            throw new NewsletterException("Brevo answered $status: $code $message", previous: $exception);
        } catch (GuzzleException $exception) {
            throw new NewsletterException('Brevo failed: ' . $exception->getMessage(), previous: $exception);
        }
    }

    public function getTemplateId(string $language): int
    {
        return $this->getForLanguage($this->templateId, $language);
    }

    private function getClient(): ClientInterface
    {
        return $this->client ??= new Client([
            'timeout' => 5,
            'connect_timeout' => 3,
        ]);
    }
}
