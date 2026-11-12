<?php

declare(strict_types=1);

namespace Hirtz\Forms\Tests\BotProtection;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use Hirtz\Forms\BotProtection\Turnstile;
use Psr\Http\Message\RequestInterface;
use Hirtz\Skeleton\Test\TestCase;

class TurnstileTest extends TestCase
{
    private ?RequestInterface $lastRequest = null;

    public function testASuccessfulVerificationPassesAndSendsTheSecret(): void
    {
        $this->getWebRequest()->setBodyParams([Turnstile::RESPONSE_PARAM => 'token']);
        $turnstile = $this->createTurnstile(new Response(200, [], '{"success":true}'));

        self::assertTrue($turnstile->verify($this->getWebRequest()));

        parse_str((string)$this->lastRequest?->getBody(), $body);
        self::assertSame('secret-key', $body['secret'] ?? null);
        self::assertSame('token', $body['response'] ?? null);
    }

    public function testARejectionFails(): void
    {
        $this->getWebRequest()->setBodyParams([Turnstile::RESPONSE_PARAM => 'token']);
        $turnstile = $this->createTurnstile(new Response(200, [], '{"success":false,"error-codes":["invalid-input-response"]}'));

        self::assertFalse($turnstile->verify($this->getWebRequest()));
    }

    public function testAnUnreachableCloudflareFailsClosed(): void
    {
        $this->getWebRequest()->setBodyParams([Turnstile::RESPONSE_PARAM => 'token']);
        $turnstile = $this->createTurnstile(new ConnectException('Timeout', new Psr7Request('POST', 'https://challenges.cloudflare.com')));

        self::assertFalse($turnstile->verify($this->getWebRequest()));
    }

    public function testAMissingOrOversizedTokenFailsWithoutARequest(): void
    {
        $turnstile = $this->createTurnstile(new Response(200, [], '{"success":true}'));

        self::assertFalse($turnstile->verify($this->getWebRequest()));

        $this->getWebRequest()->setBodyParams([Turnstile::RESPONSE_PARAM => str_repeat('x', 2049)]);
        self::assertFalse($turnstile->verify($this->getWebRequest()));

        self::assertNull($this->lastRequest);
    }

    public function testTheWidgetCarriesTheSiteKey(): void
    {
        self::assertSame('<div data-turnstile data-sitekey="site-key"></div>', $this->createTurnstile()->render());
        self::assertSame('Cloudflare Turnstile', $this->createTurnstile()->getConsentService()?->getName());
    }

    private function createTurnstile(Response|ConnectException|null $response = null): Turnstile
    {
        $handler = new MockHandler([
            function (RequestInterface $request) use ($response): Response {
                $this->lastRequest = $request;
                return $response instanceof Response ? $response : throw $response ?? new \LogicException('No response.');
            },
        ]);

        return new Turnstile('site-key', 'secret-key', new Client(['handler' => HandlerStack::create($handler)]));
    }
}
