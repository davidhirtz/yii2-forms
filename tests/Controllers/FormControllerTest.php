<?php

declare(strict_types=1);

namespace Hirtz\Forms\Tests\Controllers;

use Hirtz\Forms\BotProtection\ProtectionInterface;
use Hirtz\Forms\Controllers\FormController;
use Hirtz\Forms\Models\MailForm;
use Hirtz\Skeleton\Consent\Service;
use Hirtz\Skeleton\Helpers\Html;
use Hirtz\Skeleton\Test\TestCase;
use Hirtz\Skeleton\Web\Application;
use Hirtz\Skeleton\Web\Request;
use Override;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;

class FormControllerTest extends TestCase
{
    private bool $isHuman = true;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        Yii::$app->params['email'] = 'site@example.com';
        Yii::$app->params['formEmail'] = 'office@example.com, team@example.com';

        Yii::$app->set('botProtection', [
            'class' => \Hirtz\Forms\BotProtection\BotProtection::class,
            'protections' => fn (): array => [new StubProtection(fn (): bool => $this->isHuman)],
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->getWebRequest()->getHeaders()->set('Sec-Fetch-Site', 'same-origin');
    }

    public function testAValidSubmitSendsTheMail(): void
    {
        $data = $this->submit([
            'name' => 'Åsa Müller',
            'email' => 'asa@example.com',
            'message' => 'Привет 👋',
            'terms' => '1',
        ]);

        self::assertSame(200, $this->getWebResponse()->getStatusCode());
        self::assertSame(['status' => 200, 'message' => 'Thank you, Åsa Müller.'], $data);

        $message = $this->mailer->getLastMessage();
        self::assertNotNull($message);
        self::assertSame(['office@example.com', 'team@example.com'], array_keys($message->getTo()));
        self::assertSame(['asa@example.com'], array_keys($message->getReplyTo()));
        self::assertSame('Contact', $message->getSubject());
        self::assertStringContainsString('Message from Åsa Müller: Привет 👋', $this->mailer->getLastMessageBody());
    }

    public function testThePostCannotChangeTheMailSettings(): void
    {
        $this->submit([
            'name' => 'Åsa',
            'email' => 'asa@example.com',
            'terms' => '1',
            'to' => 'victim@example.com',
            'subject' => 'Spam',
            'viewFile' => '@app/views/secret',
        ]);

        $message = $this->mailer->getLastMessage();
        self::assertNotNull($message);
        self::assertSame(['office@example.com', 'team@example.com'], array_keys($message->getTo()));
        self::assertSame('Contact', $message->getSubject());
    }

    public function testTheMailSettingsAreNoAttributes(): void
    {
        self::assertEqualsCanonicalizing(['terms', 'name', 'email', 'message'], (new ContactForm())->attributes());
    }

    public function testAnInvalidEmailIsNoReplyTo(): void
    {
        $form = new ContactForm(['email' => 'no-email']);
        self::assertNull($form->getReplyTo());

        $form->email = 'asa@example.com';
        self::assertSame('asa@example.com', $form->getReplyTo());
    }

    public function testInvalidInputAnswersTheErrors(): void
    {
        $data = $this->submit(['name' => '', 'email' => 'no-email']);

        self::assertSame(422, $this->getWebResponse()->getStatusCode());
        self::assertSame(422, $data['status'] ?? null);
        self::assertSame(['terms', 'name', 'email'], array_keys($data['errors'] ?? []));
        self::assertNull($this->mailer->getLastMessage());
    }

    public function testAFailedBotCheckIsAFormError(): void
    {
        $this->isHuman = false;

        $data = $this->submit(['name' => 'Bot', 'email' => 'bot@example.com', 'terms' => '1']);

        self::assertSame(['The spam check failed. Please try again.'], $data['errors'][''] ?? null);
        self::assertNull($this->mailer->getLastMessage());
    }

    public function testACrossOriginPostIsForbidden(): void
    {
        $this->getWebRequest()->getHeaders()->set('Sec-Fetch-Site', 'cross-site');

        $this->expectException(ForbiddenHttpException::class);
        $this->submit([]);
    }

    public function testAGetIsRefused(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->expectException(MethodNotAllowedHttpException::class);
        $this->submit([]);
    }

    /**
     * @param array<string, string> $data
     * @return array<string, mixed>
     */
    private function submit(array $data): array
    {
        $this->getWebRequest()->setBodyParams($data);

        $controller = new FormController('form', Application::current(), [
            'forms' => ['contact' => ContactForm::class],
        ]);

        $response = $controller->runAction('contact');
        self::assertInstanceOf(\yii\web\Response::class, $response);

        /** @var array<string, mixed> $data */
        $data = $response->data;
        return $data;
    }
}

class ContactForm extends MailForm
{
    public ?string $name = null;
    public ?string $email = null;
    public ?string $message = null;

    #[Override]
    public function init(): void
    {
        $this->subject = 'Contact';
        $this->viewFile = '@forms/../tests/Data/mail/contact';

        parent::init();
    }

    #[Override]
    public function rules(): array
    {
        return [
            ...parent::rules(),
            [['name', 'email'], 'required'],
            [['email'], 'email'],
            [['message'], 'string', 'max' => 5000],
        ];
    }

    #[Override]
    public function getSuccessMessage(): string
    {
        return 'Thank you, ' . Html::encode($this->name) . '.';
    }
}

class StubProtection implements ProtectionInterface
{
    /**
     * @param \Closure(): bool $isHuman
     */
    public function __construct(private readonly \Closure $isHuman)
    {
    }

    #[Override]
    public function verify(Request $request): bool
    {
        return ($this->isHuman)();
    }

    #[Override]
    public function render(): string
    {
        return '';
    }

    #[Override]
    public function getConsentService(): ?Service
    {
        return null;
    }
}
