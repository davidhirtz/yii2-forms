<?php

declare(strict_types=1);

namespace Hirtz\Forms\Tests\BotProtection;

use Hirtz\Forms\BotProtection\Honeypot;
use Hirtz\Skeleton\Test\TestCase;
use Yii;

class HoneypotTest extends TestCase
{
    public function testAnUntouchedFormPassesOnceTheDelayIsOver(): void
    {
        $honeypot = new Honeypot(minSeconds: 0);
        $this->post($this->getRenderedFields($honeypot));

        self::assertTrue($honeypot->verify($this->getWebRequest()));
        self::assertNull($honeypot->getConsentService());
    }

    public function testAFilledFieldFails(): void
    {
        $honeypot = new Honeypot(minSeconds: 0);
        $this->post([...$this->getRenderedFields($honeypot), 'website' => 'https://spam.example']);

        self::assertFalse($honeypot->verify($this->getWebRequest()));
    }

    public function testASubmitTooSoonFails(): void
    {
        $honeypot = new Honeypot(minSeconds: 60);
        $this->post($this->getRenderedFields($honeypot));

        self::assertFalse($honeypot->verify($this->getWebRequest()));
    }

    public function testAnExpiredTimeFails(): void
    {
        $honeypot = new Honeypot(minSeconds: 0, maxSeconds: 60);
        $time = Yii::$app->getSecurity()->hashData((string)(time() - 61), $this->getWebRequest()->cookieValidationKey . Honeypot::class);
        $this->post(['website' => '', 'rendered' => $time]);

        self::assertFalse($honeypot->verify($this->getWebRequest()));
    }

    public function testAMissingOrTamperedTimeFails(): void
    {
        $honeypot = new Honeypot(minSeconds: 0);

        $this->post(['website' => '']);
        self::assertFalse($honeypot->verify($this->getWebRequest()));

        $this->post(['rendered' => (string)(time() - 3600)]);
        self::assertFalse($honeypot->verify($this->getWebRequest()));
    }

    /**
     * @return array<string, string>
     */
    private function getRenderedFields(Honeypot $honeypot): array
    {
        $html = $honeypot->render();

        self::assertStringContainsString('<div hidden aria-hidden="true">', $html);
        self::assertSame(1, preg_match('/name="rendered" value="([^"]+)"/', $html, $matches));

        return ['website' => '', 'rendered' => html_entity_decode($matches[1] ?? self::fail('No time rendered.'))];
    }

    /**
     * @param array<string, string> $data
     */
    private function post(array $data): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->getWebRequest()->setBodyParams($data);
    }
}
