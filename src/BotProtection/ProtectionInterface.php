<?php

declare(strict_types=1);

namespace Hirtz\Forms\BotProtection;

use Hirtz\Skeleton\Consent\Service;
use Hirtz\Skeleton\Web\Request;

interface ProtectionInterface
{
    /**
     * Answers `false` when the check cannot be made, too: a public form is better closed than open to spam.
     */
    public function verify(Request $request): bool;

    public function render(): string;

    public function getConsentService(): ?Service;
}
