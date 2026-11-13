<?php

declare(strict_types=1);

namespace Hirtz\Forms\Helpers;

use Yii;

final class Param
{
    public static function getString(string $name): ?string
    {
        $value = Yii::$app->params[$name] ?? null;
        return is_int($value) || (is_string($value) && $value !== '') ? (string)$value : null;
    }
}
