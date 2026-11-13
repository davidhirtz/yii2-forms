<?php

declare(strict_types=1);

namespace Hirtz\Forms\Newsletter;

trait LanguageMapTrait
{
    /**
     * @template T of int|string
     * @param T|array<string, T> $value
     * @return T
     */
    protected function getForLanguage(int|string|array $value, string $language): int|string
    {
        if (!is_array($value)) {
            return $value;
        }

        return $value[$language]
            ?? $value[strstr($language, '-', true) ?: $language]
            ?? $value[array_key_first($value)];
    }
}
