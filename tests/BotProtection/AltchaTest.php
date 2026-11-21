<?php

declare(strict_types=1);

namespace Hirtz\Forms\Tests\BotProtection;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha as AltchaLibrary;
use AltchaOrg\Altcha\Challenge;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\SolveChallengeOptions;
use Hirtz\Forms\BotProtection\Altcha;
use Hirtz\Forms\BotProtection\BotProtection;
use Hirtz\Forms\Controllers\FormController;
use Hirtz\Skeleton\Test\TestCase;
use Hirtz\Skeleton\Web\Application;
use Override;
use Yii;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class AltchaTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        Yii::$app->getCache()?->flush();
    }

    public function testASolvedChallengePassesOnce(): void
    {
        $altcha = $this->createAltcha();
        $this->post($this->solve($altcha));

        self::assertTrue($altcha->verify($this->getWebRequest()));
        self::assertFalse($altcha->verify($this->getWebRequest()));
        self::assertNull($altcha->getConsentService());
    }

    public function testAnExpiredSolutionFails(): void
    {
        $altcha = $this->createAltcha(expire: -1);
        $this->post($this->solve($altcha));

        self::assertFalse($altcha->verify($this->getWebRequest()));
    }

    public function testAChallengeSignedWithAnotherKeyFails(): void
    {
        $this->post($this->solve($this->createAltcha('another-key')));

        self::assertFalse($this->createAltcha()->verify($this->getWebRequest()));
    }

    public function testAWrongSolutionFails(): void
    {
        $altcha = $this->createAltcha();
        $payload = Payload::fromBase64($this->solve($altcha));
        $solution = $payload->solution->toArray();
        $solution['derivedKey'] = str_repeat('0', 64);

        $this->post(base64_encode((string)json_encode([
            'challenge' => $payload->challenge->toArray(),
            'solution' => $solution,
        ])));

        self::assertFalse($altcha->verify($this->getWebRequest()));
    }

    public function testAMissingOrMalformedPayloadFails(): void
    {
        $altcha = $this->createAltcha();

        self::assertFalse($altcha->verify($this->getWebRequest()));

        foreach (['not base64 🙃', base64_encode('Привет'), base64_encode('{"challenge":1}'), str_repeat('x', 4097)] as $value) {
            $this->post($value);
            self::assertFalse($altcha->verify($this->getWebRequest()));
        }
    }

    public function testTheMarkupNamesTheChallengeUrl(): void
    {
        self::assertSame('<div data-altcha data-url="/form/altcha"></div>', $this->createAltcha()->render());
    }

    public function testTheFormControllerServesAnUncachedChallenge(): void
    {
        Yii::$app->set('botProtection', [
            'class' => BotProtection::class,
            'protections' => [$this->createAltcha()],
        ]);

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->getWebRequest()->getHeaders()->set('Sec-Fetch-Site', 'same-origin');

        $response = (new FormController('form', Application::current()))->runAction('altcha');
        self::assertInstanceOf(Response::class, $response);
        self::assertSame('no-store', $response->getHeaders()->get('Cache-Control'));

        self::assertIsArray($response->data);
        $challenge = Challenge::fromArray($response->data);
        self::assertSame('PBKDF2/SHA-256', $challenge->parameters->algorithm);
        self::assertNotNull($challenge->signature);
    }

    public function testWithoutAltchaThereIsNoChallenge(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->getWebRequest()->getHeaders()->set('Sec-Fetch-Site', 'same-origin');

        $this->expectException(NotFoundHttpException::class);
        (new FormController('form', Application::current()))->runAction('altcha');
    }

    private function createAltcha(string $hmacKey = 'hmac-key', int $expire = 600): Altcha
    {
        return new Altcha($hmacKey, cost: 1, minCounter: 1, maxCounter: 20, expire: $expire);
    }

    private function solve(Altcha $altcha): string
    {
        $challenge = Challenge::fromArray($altcha->createChallenge());

        $solution = (new AltchaLibrary())->solveChallenge(new SolveChallengeOptions(
            algorithm: new Pbkdf2(),
            challenge: $challenge,
        ));

        self::assertNotNull($solution);
        return (new Payload($challenge, $solution))->toBase64();
    }

    private function post(string $value): void
    {
        $this->getWebRequest()->setBodyParams([Altcha::RESPONSE_PARAM => $value]);
    }
}
