<?php

declare(strict_types=1);

namespace Hirtz\Forms\Actions;

use Hirtz\Forms\Models\Form;
use Hirtz\Skeleton\Web\Application;
use Yii;
use yii\base\Action;
use yii\web\Response;

/**
 * Answers JSON: `{status, message}` with 200 or `{status, message, errors}` with 422.
 */
class SubmitAction extends Action
{
    /**
     * @var class-string<Form>|array{class: class-string<Form>}
     */
    public string|array $form;

    public function run(): Response
    {
        /** @var Form $form */
        $form = Yii::createObject($this->form);
        $request = Application::current()->getRequest();
        $response = Application::current()->getResponse();

        $post = $request->post();
        $isSent = is_array($post) && $form->load($post) && $form->validate() && $form->submit();

        $response->format = Response::FORMAT_JSON;
        $response->setStatusCode($isSent ? 200 : 422);

        $response->data = $isSent ? [
            'status' => 200,
            'message' => $form->getSuccessMessage(),
        ] : [
            'status' => 422,
            'message' => Yii::t('forms', 'FORM_SUBMIT_ERROR'),
            'errors' => $form->getErrors(),
        ];

        return $response;
    }
}
