<?php

declare(strict_types=1);

namespace Hirtz\Forms\Models;

use Hirtz\Forms\BotProtection\BotProtection;
use Hirtz\Skeleton\Web\Request;
use Override;
use Yii;
use yii\base\Model;

abstract class Form extends Model
{
    public string|bool $terms = false;

    abstract public function submit(): bool;

    /**
     * HTML: encode what the visitor entered.
     */
    abstract public function getSuccessMessage(): string;

    public function requiresTerms(): bool
    {
        return true;
    }

    #[Override]
    public function rules(): array
    {
        return $this->requiresTerms() ? [
            [
                ['terms'],
                'compare',
                'compareValue' => '1',
                'message' => $this->getTermsErrorMessage(),
            ],
        ] : [];
    }

    protected function getTermsErrorMessage(): string
    {
        return Yii::t('forms', 'FORM_TERMS_ERROR');
    }

    #[Override]
    public function afterValidate(): void
    {
        if (!$this->hasErrors()) {
            $request = Request::current();

            if (!$request || !BotProtection::current()->verify($request)) {
                $this->addError('', Yii::t('forms', 'FORM_BOT_PROTECTION_ERROR'));
            }
        }

        parent::afterValidate();
    }

    #[Override]
    public function formName(): string
    {
        return '';
    }
}
