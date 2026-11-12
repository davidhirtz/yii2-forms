<?php

declare(strict_types=1);

namespace Hirtz\Forms\Tests\Models;

use Hirtz\Forms\BotProtection\BotProtection;
use Hirtz\Forms\Controllers\FormController;
use Hirtz\Forms\Models\NewsletterForm;
use Hirtz\Forms\Newsletter\AlreadySubscribedException;
use Hirtz\Forms\Newsletter\Newsletter;
use Hirtz\Forms\Newsletter\NewsletterException;
use Hirtz\Forms\Newsletter\ProviderInterface;
use Hirtz\Forms\Newsletter\Subscription;
use Hirtz\Skeleton\Test\TestCase;
use Hirtz\Skeleton\Web\Application;
use Override;
use Yii;

class NewsletterFormTest extends TestCase
{
    private NewsletterStubProvider $provider;
    private bool $isEnabled = true;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new NewsletterStubProvider();

        Yii::$app->set('botProtection', ['class' => BotProtection::class]);
        Yii::$app->set('newsletter', ['class' => Newsletter::class, 'provider' => fn (): ?ProviderInterface => $this->isEnabled ? $this->provider : null]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->getWebRequest()->getHeaders()->set('Sec-Fetch-Site', 'same-origin');
        $this->getWebRequest()->getHeaders()->set('Referer', 'https://www.test.localhost/news');
    }

    public function testASubscriptionReachesTheProvider(): void
    {
        $data = $this->submit(['email' => ' asa@example.com ', 'terms' => '1']);

        self::assertSame(200, $data['status'] ?? null);
        self::assertSame(Yii::t('forms', 'NEWSLETTER_SUBSCRIBE_SUCCESS'), $data['message'] ?? null);

        $subscription = $this->provider->subscription ?? self::fail('No subscription.');
        self::assertSame('asa@example.com', $subscription->email);
        self::assertSame(Yii::$app->language, $subscription->language);
        self::assertSame('https://www.test.localhost/news', $subscription->pageUrl);
    }

    public function testAnAlreadySubscribedAddressAnswersLikeANewOne(): void
    {
        $this->provider->exception = new AlreadySubscribedException();

        self::assertSame(200, $this->submit(['email' => 'asa@example.com', 'terms' => '1'])['status'] ?? null);
    }

    public function testAProviderFailureIsAFormError(): void
    {
        $this->provider->exception = new NewsletterException('Brevo answered 500');

        $data = $this->submit(['email' => 'asa@example.com', 'terms' => '1']);

        self::assertSame(422, $data['status'] ?? null);
        self::assertSame([Yii::t('forms', 'NEWSLETTER_SUBSCRIBE_ERROR')], $data['errors'][''] ?? null);
    }

    public function testWithoutAProviderTheNewsletterIsDisabled(): void
    {
        $this->isEnabled = false;

        $data = $this->submit(['email' => 'asa@example.com', 'terms' => '1']);

        self::assertSame([Yii::t('forms', 'NEWSLETTER_DISABLED_ERROR')], $data['errors'][''] ?? null);
    }

    public function testInvalidInputNeverReachesTheProvider(): void
    {
        $data = $this->submit(['email' => 'no-email']);

        self::assertSame(['terms', 'email'], array_keys($data['errors'] ?? []));
        self::assertNull($this->provider->subscription);
    }

    /**
     * @param array<string, string> $data
     * @return array<string, mixed>
     */
    private function submit(array $data): array
    {
        $this->getWebRequest()->setBodyParams($data);

        $controller = new FormController('form', Application::current(), [
            'forms' => ['newsletter' => NewsletterForm::class],
        ]);

        $response = $controller->runAction('newsletter');
        self::assertInstanceOf(\yii\web\Response::class, $response);

        /** @var array<string, mixed> $data */
        $data = $response->data;
        return $data;
    }
}

class NewsletterStubProvider implements ProviderInterface
{
    public ?Subscription $subscription = null;
    public ?NewsletterException $exception = null;

    #[Override]
    public function subscribe(Subscription $subscription): void
    {
        $this->subscription = $subscription;

        if ($this->exception) {
            throw $this->exception;
        }
    }
}
