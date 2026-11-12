<?php

declare(strict_types=1);

namespace Hirtz\Forms\Controllers;

use Hirtz\Forms\Actions\SubmitAction;
use Hirtz\Forms\Models\Form;
use Hirtz\Skeleton\Filters\SameOriginFilter;
use Hirtz\Skeleton\Web\Controller;
use Override;
use yii\base\Module;
use yii\filters\VerbFilter;

/**
 * @extends Controller<Module>
 */
class FormController extends Controller
{
    /**
     * Action ids to the form each one submits.
     *
     * @var array<string, class-string<Form>|array{class: class-string<Form>}>
     */
    public array $forms = [];

    #[Override]
    public function behaviors(): array
    {
        return [
            ...parent::behaviors(),
            'sameOrigin' => SameOriginFilter::class,
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    '*' => ['post'],
                ],
            ],
        ];
    }

    #[Override]
    public function actions(): array
    {
        $actions = [];

        foreach ($this->forms as $id => $form) {
            $actions[$id] = [
                'class' => SubmitAction::class,
                'form' => $form,
            ];
        }

        return $actions;
    }
}
