<?php

declare(strict_types=1);

namespace Hirtz\Forms\Tests\Newsletter;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use Hirtz\Forms\Newsletter\NewsletterException;
use Hirtz\Forms\Newsletter\Subscription;
use Hirtz\Forms\Newsletter\Webhook;
use Hirtz\Skeleton\Test\TestCase;
use Psr\Http\Message\RequestInterface;

class WebhookTest extends TestCase
{
    private const string URL = 'https://flow.example.com/webhook?zapikey=secret';

    private ?RequestInterface $lastRequest = null;

    public function testASubscriptionPostsJson(): void
    {
        $this->createWebhook(new Response(200))->subscribe(new Subscription(
            'Mu' . "\u{308}" . 'ller@example.com',
            'fr',
            '127.0.0.1',
            'https://www.test.localhost/',
            ['terms' => 'YES', 'name' => 'Ярослав 漢字'],
        ));

        $request = $this->lastRequest ?? self::fail('No request.');

        self::assertSame(self::URL, (string)$request->getUri());
        self::assertSame('Bearer token', $request->getHeaderLine('Authorization'));

        self::assertSame([
            'terms' => 'YES',
            'name' => 'Ярослав 漢字',
            'email' => 'Mu' . "\u{308}" . 'ller@example.com',
            'language' => 'fr',
            'ip' => '127.0.0.1',
            'url' => 'https://www.test.localhost/',
        ], json_decode((string)$request->getBody(), true));
    }

    public function testAFailureKeepsTheUrlOutOfTheMessage(): void
    {
        foreach ([new Response(500), new ConnectException('cURL error 28 for ' . self::URL, new Psr7Request('POST', self::URL))] as $response) {
            try {
                $this->createWebhook($response)->subscribe(new Subscription('asa@example.com', 'de'));
                self::fail('No exception.');
            } catch (NewsletterException $exception) {
                self::assertStringNotContainsString('secret', $exception->getMessage());
                self::assertNull($exception->getPrevious());
            }
        }
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
