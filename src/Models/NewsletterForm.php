<?php

declare(strict_types=1);

namespace Hirtz\Forms\Models;

use Hirtz\Forms\Newsletter\AlreadySubscribedException;
use Hirtz\Forms\Newsletter\Newsletter;
use Hirtz\Forms\Newsletter\NewsletterException;
use Hirtz\Forms\Newsletter\Subscription;
use Hirtz\Skeleton\Web\Request;
use Override;
use Yii;

class NewsletterForm extends Form
{
    public ?string $email = null;

    #[Override]
    public function rules(): array
    {
        return [
            ...parent::rules(),
            [
                ['email'],
                'filter',
                'filter' => fn (mixed $value): mixed => is_string($value) ? trim($value) : $value,
            ],
            [
                ['email'],
                'required',
            ],
            [
                ['email'],
                'string',
                'max' => 255,
            ],
            [
                ['email'],
                'email',
            ],
        ];
    }

    #[Override]
    public function submit(): bool
    {
        $provider = Newsletter::current()->getProvider();

        if (!$provider) {
            $this->addError('', Yii::t('forms', 'NEWSLETTER_DISABLED_ERROR'));
            return false;
        }

        try {
            $provider->subscribe($this->getSubscription());
        } catch (AlreadySubscribedException) {
            // Answers like a new subscription, so the form never reveals who is subscribed.
        } catch (NewsletterException $exception) {
            Yii::error($exception->getMessage(), __METHOD__);
            $this->addError('', Yii::t('forms', 'NEWSLETTER_SUBSCRIBE_ERROR'));
            return false;
        }

        return true;
    }

    public function getSubscription(): Subscription
    {
        $request = Request::current();

        return new Subscription(
            (string)$this->email,
            Yii::$app->language,
            $request?->getUserIP(),
            $request?->getReferrer(),
            $this->getSubscriptionAttributes(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getSubscriptionAttributes(): array
    {
        return [];
    }

    #[Override]
    public function getSuccessMessage(): string
    {
        return Yii::t('forms', 'NEWSLETTER_SUBSCRIBE_SUCCESS');
    }

    #[Override]
    public function attributeLabels(): array
    {
        return [
            'email' => Yii::t('forms', 'NEWSLETTER_EMAIL_LABEL'),
        ];
    }
}
