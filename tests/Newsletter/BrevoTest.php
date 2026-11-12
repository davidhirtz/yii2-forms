<?php

declare(strict_types=1);

namespace Hirtz\Forms\Tests\Newsletter;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use Hirtz\Forms\Newsletter\AlreadySubscribedException;
use Hirtz\Forms\Newsletter\Brevo;
use Hirtz\Forms\Newsletter\NewsletterException;
use Hirtz\Forms\Newsletter\Subscription;
use Hirtz\Skeleton\Test\TestCase;
use Psr\Http\Message\RequestInterface;
use yii\base\InvalidConfigException;

class BrevoTest extends TestCase
{
    private ?RequestInterface $lastRequest = null;

    public function testASubscriptionSendsTheDoubleOptIn(): void
    {
        $brevo = $this->createBrevo(new Response(201));
        $brevo->subscribe(new Subscription('åsa@example.com', 'de', attributes: ['FIRSTNAME' => 'Åsa 👋']));

        $request = $this->lastRequest ?? self::fail('No request.');

        self::assertSame('https://api.brevo.com/v3/contacts/doubleOptinConfirmation', (string)$request->getUri());
        self::assertSame('api-key', $request->getHeaderLine('api-key'));

        self::assertSame([
            'email' => 'åsa@example.com',
            'attributes' => ['FIRSTNAME' => 'Åsa 👋'],
            'includeListIds' => [3, 5],
            'templateId' => 7,
            'redirectionUrl' => 'https://www.test.localhost/subscribed',
        ], json_decode((string)$request->getBody(), true));
    }

    public function testTheTemplateFollowsTheLanguage(): void
    {
        $brevo = $this->createBrevo(new Response(201), ['templateId' => ['de' => 8, 'en-US' => 9]]);

        self::assertSame(8, $brevo->getTemplateId('de'));
        self::assertSame(9, $brevo->getTemplateId('en-US'));
        self::assertSame(8, $brevo->getTemplateId('fr'));
    }

    public function testADuplicateIsAlreadySubscribed(): void
    {
        $brevo = $this->createBrevo(new Response(400, [], '{"code":"duplicate_parameter","message":"Contact already exist"}'));

        $this->expectException(AlreadySubscribedException::class);
        $brevo->subscribe(new Subscription('asa@example.com', 'de'));
    }

    public function testAnErrorNamesBrevosCode(): void
    {
        $brevo = $this->createBrevo(new Response(401, [], '{"code":"unauthorized","message":"Key not found"}'));

        $this->expectException(NewsletterException::class);
        $this->expectExceptionMessage('Brevo answered 401: unauthorized Key not found');
        $brevo->subscribe(new Subscription('asa@example.com', 'de'));
    }

    public function testAnUnreachableBrevoFails(): void
    {
        $brevo = $this->createBrevo(new ConnectException('Timeout', new Psr7Request('POST', 'https://api.brevo.com')));

        $this->expectException(NewsletterException::class);
        $brevo->subscribe(new Subscription('asa@example.com', 'de'));
    }

    public function testAMissingTemplateIsAConfigurationError(): void
    {
        $this->expectException(InvalidConfigException::class);
        new Brevo(['apiKey' => 'api-key', 'listIds' => [3]]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createBrevo(Response|ConnectException $response, array $config = []): Brevo
    {
        $handler = new MockHandler([
            function (RequestInterface $request) use ($response): Response {
                $this->lastRequest = $request;
                return $response instanceof Response ? $response : throw $response;
            },
        ]);

        return new Brevo([
            'apiKey' => 'api-key',
            'listIds' => [3, 5],
            'templateId' => 7,
            'redirectUrl' => 'https://www.test.localhost/subscribed',
            'client' => new Client(['handler' => HandlerStack::create($handler)]),
            ...$config,
        ]);
    }
}
