<?php

declare(strict_types=1);

namespace Hirtz\Forms\Models;

use Hirtz\Forms\Helpers\Param;
use Override;
use Yii;
use yii\base\InvalidConfigException;
use yii\validators\EmailValidator;

abstract class MailForm extends Form
{
    /**
     * @var list<string>|string|null
     */
    public array|string|null $to = null;

    public ?string $from = null;

    public ?string $subject = null;

    public ?string $viewFile = null;

    #[Override]
    public function init(): void
    {
        $this->to ??= Param::getString('formEmail') ?? Param::getString('email');
        $this->from ??= Param::getString('email');

        parent::init();
    }

    /**
     * The mail settings are properties, never attributes a post could load or a view list.
     */
    #[Override]
    public function attributes(): array
    {
        return array_values(array_diff(parent::attributes(), ['to', 'from', 'subject', 'viewFile']));
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

    public function getReplyTo(): ?string
    {
        $email = $this->getAttributes(['email'])['email'] ?? null;
        return is_string($email) && (new EmailValidator())->validate($email) ? $email : null;
    }
}
