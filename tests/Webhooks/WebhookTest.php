<?php

declare(strict_types=1);

namespace Hirtz\Forms\Tests\Webhooks;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use Hirtz\Forms\Webhooks\Webhook;
use Hirtz\Forms\Webhooks\WebhookException;
use Hirtz\Skeleton\Test\TestCase;
use Psr\Http\Message\RequestInterface;
use yii\base\InvalidConfigException;

class WebhookTest extends TestCase
{
    private const string URL = 'https://flow.example.com/webhook?zapikey=secret';

    private ?RequestInterface $lastRequest = null;

    public function testThePostIsJson(): void
    {
        $this->createWebhook(new Response(200))->post(['name' => 'Ярослав 漢字 👋', 'email' => 'Mu' . "\u{308}" . 'ller@example.com']);

        $request = $this->lastRequest ?? self::fail('No request.');

        self::assertSame('POST', $request->getMethod());
        self::assertSame(self::URL, (string)$request->getUri());
        self::assertSame('Bearer token', $request->getHeaderLine('Authorization'));
        self::assertSame(['name' => 'Ярослав 漢字 👋', 'email' => 'Mu' . "\u{308}" . 'ller@example.com'], json_decode((string)$request->getBody(), true));
    }

    public function testAFailureKeepsTheUrlOutOfTheMessage(): void
    {
        foreach ([new Response(500), new ConnectException('cURL error 28 for ' . self::URL, new Psr7Request('POST', self::URL))] as $response) {
            try {
                $this->createWebhook($response)->post([]);
                self::fail('No exception.');
            } catch (WebhookException $exception) {
                self::assertStringNotContainsString('secret', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
    }

    public function testAMissingUrlIsAConfigurationError(): void
    {
        $this->expectException(InvalidConfigException::class);
        new Webhook();
    }

    private function createWebhook(Response|ConnectException $response): Webhook
    {
        $handler = new MockHandler([
            function (RequestInterface $request) use ($response): Response {
                $this->lastRequest = $request;
                return $response instanceof Response ? $response : throw $response;
            },
        ]);

        return new Webhook([
            'url' => self::URL,
            'headers' => ['Authorization' => 'Bearer token'],
            'client' => new Client(['handler' => HandlerStack::create($handler)]),
        ]);
    }
}
