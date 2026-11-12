<?php

declare(strict_types=1);

namespace Hirtz\Forms\Models;

use Override;
use Yii;
use yii\base\InvalidConfigException;

/**
 * Sends the form to `to` through the project's mail view, which receives the form as `$form`.
 */
abstract class MailForm extends Form
{
    /**
     * Defaults to `params.formEmail`, then `params.email`; a string may list several, separated by commas.
     *
     * @var list<string>|string|null
     */
    public array|string|null $to = null;

    /**
     * Defaults to `params.email`.
     */
    public ?string $from = null;

    public ?string $subject = null;

    public ?string $viewFile = null;

    #[Override]
    public function init(): void
    {
        $params = Yii::$app->params;

        $this->to ??= $this->getParam($params, 'formEmail') ?? $this->getParam($params, 'email');
        $this->from ??= $this->getParam($params, 'email');

        parent::init();
    }

    #[Override]
    public function submit(): bool
    {
        if (!$this->to || !$this->from || !$this->subject || !$this->viewFile) {
            throw new InvalidConfigException(static::class . ' needs "to", "from", "subject" and "viewFile".');
        }

        $message = Yii::$app->getMailer()->compose($this->viewFile, ['form' => $this])
            ->setSubject($this->subject)
            ->setFrom($this->from)
            ->setTo($this->getRecipients());

        if ($replyTo = $this->getReplyTo()) {
            $message->setReplyTo($replyTo);
        }

        if (!$message->send()) {
            $this->addError('', Yii::t('forms', 'FORM_SEND_ERROR'));
            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function getRecipients(): array
    {
        $to = is_string($this->to) ? explode(',', $this->to) : (array)$this->to;
        return array_values(array_filter(array_map(trim(...), $to)));
    }

    /**
     * The visitor's address, so a reply reaches them.
     */
    public function getReplyTo(): ?string
    {
        $email = $this->getAttributes(['email'])['email'] ?? null;
        return is_string($email) && $email !== '' && !$this->hasErrors('email') ? $email : null;
    }

    /**
     * @param array<array-key, mixed> $params
     */
    private function getParam(array $params, string $name): ?string
    {
        $value = $params[$name] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }
}
