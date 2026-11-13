<?php

declare(strict_types=1);

namespace Hirtz\Forms\Tests\Models;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Hirtz\Forms\BotProtection\BotProtection;
use Hirtz\Forms\Controllers\FormController;
use Hirtz\Forms\Models\WebhookForm;
use Hirtz\Forms\Webhooks\Webhook;
use Hirtz\Skeleton\Test\TestCase;
use Hirtz\Skeleton\Web\Application;
use Override;
use Psr\Http\Message\RequestInterface;
use Yii;

class WebhookFormTest extends TestCase
{
    private ?RequestInterface $lastRequest = null;
    private Response $response;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->response = new Response(200);

        Yii::$app->params['formWebhookUrl'] = 'https://flow.example.com/contact?key=secret';
        Yii::$app->set('botProtection', ['class' => BotProtection::class, 'protections' => []]);

        $handler = new MockHandler([
            function (RequestInterface $request): Response {
                $this->lastRequest = $request;
                return $this->response;
            },
        ]);

        Yii::$container->set(Webhook::class, ['client' => new Client(['handler' => HandlerStack::create($handler)])]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->getWebRequest()->getHeaders()->set('Sec-Fetch-Site', 'same-origin');
        $this->getWebRequest()->getHeaders()->set('Referer', 'https://www.test.localhost/contact');
    }

    public function testASubmitPostsTheAttributes(): void
    {
        $data = $this->submit([
            'name' => 'Åsa Ярослав 漢字 👋',
            'message' => 'Mu' . "\u{308}" . 'ller',
            'terms' => '1',
            'webhookUrl' => 'https://attacker.example.com/',
        ]);

        self::assertSame(200, $data['status'] ?? null);

        $request = $this->lastRequest ?? self::fail('No request.');
        self::assertSame('https://flow.example.com/contact?key=secret', (string)$request->getUri());

        self::assertEquals([
            'name' => 'Åsa Ярослав 漢字 👋',
            'message' => 'Mu' . "\u{308}" . 'ller',
            'terms' => '1',
            'language' => Yii::$app->language,
            'ip' => null,
            'url' => 'https://www.test.localhost/contact',
        ], json_decode((string)$request->getBody(), true));
    }

    public function testAFailedPostIsAFormError(): void
    {
        $this->response = new Response(500);

        $data = $this->submit(['name' => 'Åsa', 'terms' => '1']);

        self::assertSame(422, $data['status'] ?? null);
        self::assertSame([Yii::t('forms', 'FORM_SEND_ERROR')], $data['errors'][''] ?? null);
    }

    /**
     * @param array<string, string> $data
     * @return array<string, mixed>
     */
    private function submit(array $data): array
    {
        $this->getWebRequest()->setBodyParams($data);

        $controller = new FormController('form', Application::current(), [
            'forms' => ['contact' => ContactWebhookForm::class],
        ]);

        $response = $controller->runAction('contact');
        self::assertInstanceOf(\yii\web\Response::class, $response);

        /** @var array<string, mixed> $data */
        $data = $response->data;
        return $data;
    }
}

class ContactWebhookForm extends WebhookForm
{
    public ?string $name = null;
    public ?string $message = null;

    #[Override]
    public function rules(): array
    {
        return [
            ...parent::rules(),
            [['name'], 'required'],
            [['message'], 'string'],
        ];
    }

    #[Override]
    public function getSuccessMessage(): string
    {
        return 'Thanks.';
    }
}
