<?php

declare(strict_types=1);

namespace Hirtz\Forms\Actions;

use Hirtz\Forms\BotProtection\Altcha;
use Hirtz\Forms\BotProtection\BotProtection;
use Hirtz\Skeleton\Web\Application;
use yii\base\Action;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * A page holding the form may be cached, so its challenge comes from here.
 */
class ChallengeAction extends Action
{
    public function run(): Response
    {
        $altcha = BotProtection::current()->findProtection(Altcha::class)
            ?? throw new NotFoundHttpException();

        $response = Application::current()->getResponse();
        $response->format = Response::FORMAT_JSON;
        $response->getHeaders()->set('Cache-Control', 'no-store');
        $response->data = $altcha->createChallenge();

        return $response;
    }
}
