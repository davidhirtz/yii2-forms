<?php

declare(strict_types=1);

namespace Hirtz\Forms\BotProtection;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha as AltchaLibrary;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\Payload;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Hirtz\Skeleton\Consent\Service;
use Hirtz\Skeleton\Helpers\Url;
use Hirtz\Skeleton\Html\Div;
use Hirtz\Skeleton\Web\Request;
use InvalidArgumentException;
use Override;
use SensitiveParameter;
use Yii;

class Altcha implements ProtectionInterface
{
    final public const string RESPONSE_PARAM = 'altcha';

    /**
     * @param array<int|string, mixed>|string $challengeRoute
     */
    public function __construct(
        #[SensitiveParameter] private readonly string $hmacKey,
        public array|string $challengeRoute = ['/form/altcha'],
        public int $cost = 5000,
        public int $minCounter = 500,
        public int $maxCounter = 1500,
        public int $expire = 600,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function createChallenge(): array
    {
        return $this->getAltcha()->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2(),
            cost: $this->cost,
            counter: random_int($this->minCounter, $this->maxCounter),
            expiresAt: time() + $this->expire,
        ))->toArray();
    }

    #[Override]
    public function verify(Request $request): bool
    {
        $value = $request->post(self::RESPONSE_PARAM);

        if (!is_string($value) || $value === '' || strlen($value) > 4096) {
            return false;
        }

        try {
            $payload = Payload::fromBase64($value);
        } catch (InvalidArgumentException) {
            return false;
        }

        $result = $this->getAltcha()->verifySolution(new VerifySolutionOptions(
            algorithm: new Pbkdf2(),
            payload: $payload,
        ));

        if (!$result->verified) {
            Yii::info('Altcha rejected: ' . ($result->expired ? 'expired' : 'invalid'), __METHOD__);
            return false;
        }

        $ttl = (int)ceil(($payload->challenge->parameters->expiresAt ?? 0) - time());
        $cache = Yii::$app->getCache();

        if ($ttl <= 0 || !$cache?->add([self::class, $payload->challenge->signature], true, $ttl)) {
            Yii::info('Altcha rejected: replayed', __METHOD__);
            return false;
        }

        return true;
    }

    #[Override]
    public function render(): string
    {
        return Div::make()
            ->attributes([
                'data-altcha' => true,
                'data-url' => Url::to($this->challengeRoute),
            ])
            ->render();
    }

    #[Override]
    public function getConsentService(): ?Service
    {
        return null;
    }

    private function getAltcha(): AltchaLibrary
    {
        return new AltchaLibrary(
            hmacSignatureSecret: $this->hmacKey,
            hmacKeySignatureSecret: hash_hmac('sha256', 'keySignature', $this->hmacKey),
        );
    }
}
