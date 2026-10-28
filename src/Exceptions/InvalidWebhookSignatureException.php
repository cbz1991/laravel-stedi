<?php

namespace Cabuz\Stedi\Exceptions;

/**
 * A webhook request did not carry a valid Stedi signature.
 */
class InvalidWebhookSignatureException extends StediException
{
    public static function because(string $reason): self
    {
        return new self("Invalid Stedi webhook signature: {$reason}.");
    }
}
