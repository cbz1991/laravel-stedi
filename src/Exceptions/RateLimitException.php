<?php

namespace Cabuz\Stedi\Exceptions;

/**
 * Too many requests (429), after all retries were used.
 */
class RateLimitException extends StediException
{
    /**
     * Seconds to wait before retrying, from the Retry-After header.
     */
    public function retryAfter(): ?int
    {
        $header = $this->response?->header('Retry-After');

        return is_numeric($header) ? (int) $header : null;
    }
}
