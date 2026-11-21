<?php

declare(strict_types=1);

namespace Hirtz\Forms\Newsletter;

/**
 * The provider refuses the address itself (fake, or signed up to too many lists), not the request.
 */
class RejectedAddressException extends NewsletterException
{
}
