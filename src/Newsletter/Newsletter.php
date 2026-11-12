<?php

declare(strict_types=1);

namespace Hirtz\Forms\Newsletter;

use Closure;
use Yii;
use yii\base\Component;

class Newsletter extends Component
{
    /**
     * A closure, so that a project's provider replaces the one the bundle builds from params.
     *
     * @var Closure(): (ProviderInterface|null)|ProviderInterface|null
     */
    public Closure|ProviderInterface|null $provider = null;

    public static function current(): self
    {
        /** @var self $component */
        $component = Yii::$app->get('newsletter');
        return $component;
    }

    public function getProvider(): ?ProviderInterface
    {
        if ($this->provider instanceof Closure) {
            $this->provider = ($this->provider)();
        }

        return $this->provider;
    }
}
