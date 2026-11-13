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

class Mailchimp extends BaseObject implements ProviderInterface
{
    use LanguageMapTrait;

    public string $apiKey = '';

    /**
     * The audience, or one per language with the first as fallback.
     *
     * @var string|array<string, string>
     */
    public string|array $listId = '';

    /**
     * The data center, such as `us21`; defaults to the API key's suffix.
     */
    public ?string $server = null;

    /**
     * A new address stays `pending` until it confirms Mailchimp's email; without, it is `subscribed` at once.
     */
    public bool $doubleOptIn = true;

    public ?ClientInterface $client = null;

    #[Override]
    public function init(): void
    {
        $this->server ??= str_contains($this->apiKey, '-') ? substr($this->apiKey, strrpos($this->apiKey, '-') + 1) : null;

        if ($this->apiKey === '' || !$this->listId || !$this->server) {
            throw new InvalidConfigException(static::class . ' needs "apiKey", "listId" and a "server" or an API key ending in one.');
        }

        parent::init();
    }

    #[Override]
    public function subscribe(Subscription $subscription): void
    {
        $listId = $this->getListId($subscription->language);
        $member = md5(mb_strtolower($subscription->email));

        try {
            $this->getClient()->request('PUT', "https://$this->server.api.mailchimp.com/3.0/lists/$listId/members/$member", [
                'auth' => ['anystring', $this->apiKey],
                'json' => array_filter([
                    'email_address' => $subscription->email,
                    'status_if_new' => $this->doubleOptIn ? 'pending' : 'subscribed',
                    'merge_fields' => $subscription->attributes,
                    'ip_signup' => $subscription->ip,
                ]),
            ]);
        } catch (RequestException $exception) {
            $data = json_decode((string)$exception->getResponse()?->getBody(), true);
            $title = is_array($data) && is_string($data['title'] ?? null) ? $data['title'] : '';
            $detail = is_array($data) && is_string($data['detail'] ?? null) ? $data['detail'] : '';
            $status = $exception->getResponse()?->getStatusCode() ?? 0;

            throw new NewsletterException("Mailchimp answered $status: $title $detail", previous: $exception);
        } catch (GuzzleException $exception) {
            throw new NewsletterException('Mailchimp failed: ' . $exception->getMessage(), previous: $exception);
        }
    }

    public function getListId(string $language): string
    {
        return $this->getForLanguage($this->listId, $language);
    }

    private function getClient(): ClientInterface
    {
        return $this->client ??= new Client([
            'timeout' => 5,
            'connect_timeout' => 3,
        ]);
    }
}
