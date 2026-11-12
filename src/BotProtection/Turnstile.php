<?php

declare(strict_types=1);

namespace Hirtz\Forms\BotProtection;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Hirtz\Skeleton\Consent\Service;
use Hirtz\Skeleton\Helpers\Html;
use Hirtz\Skeleton\Web\Request;
use Override;
use Yii;

class Turnstile implements ProtectionInterface
{
    final public const string RESPONSE_PARAM = 'cf-turnstile-response';
    private const string VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct(
        public readonly string $siteKey,
        #[\SensitiveParameter] private readonly string $secretKey,
        private ?ClientInterface $client = null,
    ) {
    }

    #[Override]
    public function verify(Request $request): bool
    {
        $response = $request->post(self::RESPONSE_PARAM);

        if (!is_string($response) || $response === '' || strlen($response) > 2048) {
            return false;
        }

        try {
            $result = $this->getClient()->request('POST', self::VERIFY_URL, [
                'form_params' => array_filter([
                    'secret' => $this->secretKey,
                    'response' => $response,
                    'remoteip' => $request->getUserIP(),
                ]),
            ]);

            $data = json_decode((string)$result->getBody(), true);
        } catch (GuzzleException $exception) {
            Yii::error('Turnstile verification failed: ' . $exception->getMessage(), __METHOD__);
            return false;
        }

        if (!is_array($data) || ($data['success'] ?? false) !== true) {
            $codes = is_array($data) && is_array($data['error-codes'] ?? null) ? $data['error-codes'] : [];
            Yii::info('Turnstile rejected: ' . implode(', ', array_filter($codes, is_string(...))), __METHOD__);
            return false;
        }

        return true;
    }

    #[Override]
    public function render(): string
    {
        return Html::tag('div', '', [
            'data-turnstile' => true,
            'data-sitekey' => $this->siteKey,
        ]);
    }

    #[Override]
    public function getConsentService(): ?Service
    {
        return Service::make()
            ->name('Cloudflare Turnstile')
            ->provider('Cloudflare, Inc.')
            ->purpose(Yii::t('forms', 'BOT_PROTECTION_TURNSTILE_TEXT'))
            ->privacyUrl('https://www.cloudflare.com/privacypolicy/');
    }

    private function getClient(): ClientInterface
    {
        return $this->client ??= new Client([
            'timeout' => 5,
            'connect_timeout' => 3,
        ]);
    }
}
