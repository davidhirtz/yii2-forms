<?php

declare(strict_types=1);

namespace Hirtz\Forms\Tests\BotProtection;

use Hirtz\Forms\BotProtection\Honeypot;
use Hirtz\Skeleton\Test\TestCase;
use Hirtz\Skeleton\Web\Application;
use ReflectionClass;
use Yii;
use yii\filters\PageCache;
use yii\widgets\FragmentCache;

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

    public function testATokenOlderThanItsMaximumFails(): void
    {
        $honeypot = new Honeypot(minSeconds: 0);

        $this->post(['website' => '', 'rendered' => $this->sign((time() - 60) . ':61')]);
        self::assertTrue($honeypot->verify($this->getWebRequest()));

        $this->post(['website' => '', 'rendered' => $this->sign((time() - 62) . ':61')]);
        self::assertFalse($honeypot->verify($this->getWebRequest()));
    }

    public function testATokenWithoutMaximumNeverExpires(): void
    {
        $honeypot = new Honeypot(minSeconds: 0);

        $this->post(['website' => '', 'rendered' => $this->sign((time() - 86400 * 365) . ':0')]);
        self::assertTrue($honeypot->verify($this->getWebRequest()));

        $this->post(['website' => '', 'rendered' => $this->sign((string)(time() - 86400 * 365))]);
        self::assertTrue($honeypot->verify($this->getWebRequest()), 'A token from before the maximum.');
    }

    public function testTheMaximumAddsTheCachesAroundTheForm(): void
    {
        $view = Application::current()->getView();

        self::assertSame(60, $this->getRenderedMaxAge(new Honeypot(maxSeconds: 60)));
        self::assertSame(0, $this->getRenderedMaxAge(new Honeypot(maxSeconds: null)));

        $view->pushDynamicContent(new PageCache(['duration' => 3600]));
        $fragmentCache = (new ReflectionClass(FragmentCache::class))->newInstanceWithoutConstructor();
        $fragmentCache->duration = 600;
        $view->pushDynamicContent($fragmentCache);
        self::assertSame(4260, $this->getRenderedMaxAge(new Honeypot(maxSeconds: 60)));
        $view->popDynamicContent();
        $view->popDynamicContent();

        $view->pushDynamicContent(new PageCache(['duration' => 0]));
        self::assertSame(0, $this->getRenderedMaxAge(new Honeypot(maxSeconds: 60)));
        $view->popDynamicContent();
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

    private function getRenderedMaxAge(Honeypot $honeypot): int
    {
        $token = Yii::$app->getSecurity()->validateData($this->getRenderedFields($honeypot)['rendered'], $this->getKey());
        self::assertIsString($token);

        return (int)explode(':', $token)[1];
    }

    private function sign(string $data): string
    {
        return Yii::$app->getSecurity()->hashData($data, $this->getKey());
    }

    private function getKey(): string
    {
        return $this->getWebRequest()->cookieValidationKey . Honeypot::class;
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
