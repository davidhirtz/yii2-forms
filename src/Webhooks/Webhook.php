<?php

declare(strict_types=1);

namespace Hirtz\Forms\Webhooks;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Override;
use yii\base\BaseObject;
use yii\base\InvalidConfigException;

class Webhook extends BaseObject
{
    /**
     * Often carries its key in the query string, so it never reaches a log.
     */
    public string $url = '';

    /**
     * @var array<string, string>
     */
    public array $headers = [];

    public ?ClientInterface $client = null;

    #[Override]
    public function init(): void
    {
        if ($this->url === '') {
            throw new InvalidConfigException(static::class . ' needs "url".');
        }

        parent::init();
    }

    /**
     * @param array<string, mixed> $data
     * @throws WebhookException
     */
    public function post(array $data): void
    {
        try {
            $this->getClient()->request('POST', $this->url, [
                'headers' => $this->headers,
                'json' => $data,
            ]);
        } catch (GuzzleException $exception) {
            $status = $exception instanceof RequestException ? $exception->getResponse()?->getStatusCode() : null;
            throw new WebhookException('Webhook failed: ' . ($status ? "HTTP $status" : $exception::class));
        }
    }

    private function getClient(): ClientInterface
    {
        return $this->client ??= new Client([
            'timeout' => 5,
            'connect_timeout' => 3,
        ]);
    }
}
