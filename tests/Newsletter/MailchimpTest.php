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
use Hirtz\Forms\Newsletter\Mailchimp;
use Hirtz\Forms\Newsletter\NewsletterException;
use Hirtz\Forms\Newsletter\Subscription;
use Hirtz\Skeleton\Test\TestCase;
use Psr\Http\Message\RequestInterface;
use yii\base\InvalidConfigException;

class MailchimpTest extends TestCase
{
    private ?RequestInterface $lastRequest = null;

    public function testASubscriptionPostsAPendingMember(): void
    {
        $this->createMailchimp(new Response(200))->subscribe(new Subscription(
            'Åsa@Example.com',
            'de',
            '127.0.0.1',
            attributes: ['FNAME' => 'Åsa', 'LNAME' => 'Ярослав 漢字 👋'],
        ));

        $request = $this->lastRequest ?? self::fail('No request.');

        self::assertSame('POST', $request->getMethod());
        self::assertSame('https://us21.api.mailchimp.com/3.0/lists/list-1/members', (string)$request->getUri());
        self::assertSame('Basic ' . base64_encode('anystring:key-us21'), $request->getHeaderLine('Authorization'));

        self::assertSame([
            'email_address' => 'Åsa@Example.com',
            'status' => 'pending',
            'merge_fields' => ['FNAME' => 'Åsa', 'LNAME' => 'Ярослав 漢字 👋'],
            'ip_signup' => '127.0.0.1',
        ], json_decode((string)$request->getBody(), true));
    }

    public function testWithoutDoubleOptInTheMemberIsSubscribed(): void
    {
        $this->createMailchimp(new Response(200), ['doubleOptIn' => false])->subscribe(new Subscription('asa@example.com', 'de'));

        $body = json_decode((string)$this->lastRequest?->getBody(), true);
        self::assertSame(['email_address' => 'asa@example.com', 'status' => 'subscribed'], $body);
    }

    public function testTheListFollowsTheLanguage(): void
    {
        $mailchimp = $this->createMailchimp(new Response(200), ['listId' => ['de' => 'list-de', 'en' => 'list-en']]);

        self::assertSame('list-de', $mailchimp->getListId('de'));
        self::assertSame('list-en', $mailchimp->getListId('en-US'));
        self::assertSame('list-de', $mailchimp->getListId('fr'));
    }

    public function testAServerOverridesTheKeysSuffix(): void
    {
        $this->createMailchimp(new Response(200), ['apiKey' => 'key', 'server' => 'us5'])->subscribe(new Subscription('asa@example.com', 'de'));

        self::assertSame('us5.api.mailchimp.com', $this->lastRequest?->getUri()->getHost());
    }

    public function testAnErrorNamesMailchimpsTitleButNotTheAddress(): void
    {
        $mailchimp = $this->createMailchimp(new Response(400, [], '{"title":"Invalid Resource","status":400,"detail":"asa@example.com looks fake or invalid."}'));

        try {
            $mailchimp->subscribe(new Subscription('asa@example.com', 'de'));
            self::fail('No exception.');
        } catch (NewsletterException $exception) {
            self::assertSame('Mailchimp answered 400: Invalid Resource', $exception->getMessage());
        }
    }

    public function testAnExistingMemberIsAlreadySubscribed(): void
    {
        $mailchimp = $this->createMailchimp(new Response(400, [], '{"title":"Member Exists","status":400,"detail":"asa@example.com is already a list member."}'));

        $this->expectException(AlreadySubscribedException::class);
        $mailchimp->subscribe(new Subscription('asa@example.com', 'de'));
    }

    public function testAnUnreachableMailchimpFails(): void
    {
        $mailchimp = $this->createMailchimp(new ConnectException('Timeout', new Psr7Request('POST', 'https://us21.api.mailchimp.com')));

        $this->expectException(NewsletterException::class);
        $mailchimp->subscribe(new Subscription('asa@example.com', 'de'));
    }

    public function testAKeyWithoutServerIsAConfigurationError(): void
    {
        $this->expectException(InvalidConfigException::class);
        new Mailchimp(['apiKey' => 'key', 'listId' => 'list-1']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createMailchimp(Response|ConnectException $response, array $config = []): Mailchimp
    {
        $handler = new MockHandler([
            function (RequestInterface $request) use ($response): Response {
                $this->lastRequest = $request;
                return $response instanceof Response ? $response : throw $response;
            },
        ]);

        return new Mailchimp([
            'apiKey' => 'key-us21',
            'listId' => 'list-1',
            'client' => new Client(['handler' => HandlerStack::create($handler)]),
            ...$config,
        ]);
    }
}
