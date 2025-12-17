<?php

declare(strict_types=1);

namespace Inkvizitoria\MonoParts\Exceptions;

use Throwable;

/**
 * Wraps lower level transport errors (network timeouts, invalid JSON, etc.).
 */
final class TransportException extends MonoPartsException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
