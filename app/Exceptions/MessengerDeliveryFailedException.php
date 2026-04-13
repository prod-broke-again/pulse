<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Outbound message was persisted but delivery to the external network (VK, Telegram, …) failed.
 */
final class MessengerDeliveryFailedException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?Throwable $previousException = null,
    ) {
        parent::__construct($message, 0, $previousException);
    }
}
