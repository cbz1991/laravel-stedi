<?php

namespace Cabuz\Stedi\Exceptions;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * Base exception for every error thrown by the package.
 *
 * Catch this class to handle any Stedi failure, or one of the subclasses to
 * handle a specific HTTP status.
 */
class StediException extends RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?Response $response = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Build the matching exception for a failed API response.
     */
    public static function fromResponse(Response $response, string $method, string $url): self
    {
        $status = $response->status();
        $error = $response->json('message');
        $error = is_string($error) && $error !== '' ? $error : ($response->reason() ?: 'Unknown error');

        $message = sprintf('Stedi API %s %s failed with %d: %s', $method, $url, $status, $error);

        $class = match (true) {
            $status === 400, $status === 413, $status === 422 => InvalidRequestException::class,
            $status === 401 => AuthenticationException::class,
            $status === 403 => PermissionDeniedException::class,
            $status === 404 => NotFoundException::class,
            $status === 409 => ConflictException::class,
            $status === 429 => RateLimitException::class,
            $status >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class($message, $status, null, $response);
    }

    /**
     * Stedi's error message, if any.
     */
    public function error(): ?string
    {
        $error = $this->response?->json('message');

        return is_string($error) ? $error : null;
    }

    /**
     * Stedi's error code (e.g. "access_denied"), if any.
     */
    public function errorCode(): ?string
    {
        $code = $this->response?->json('code');

        return is_string($code) ? $code : null;
    }

    /**
     * Field-level validation errors: a list of ['path' => ..., 'message' => ...].
     * Stedi sends them as fieldList or, on newer endpoints, as errors (which
     * also holds a payer's AAA rejections; those have a code and are skipped).
     *
     * @return list<array<string, mixed>>
     */
    public function fieldErrors(): array
    {
        $fields = $this->response?->json('fieldList') ?? $this->response?->json('errors');

        if (! is_array($fields)) {
            return [];
        }

        return array_values(array_filter($fields, fn ($field) => is_array($field) && isset($field['message']) && ! isset($field['code'])));
    }

    public function status(): ?int
    {
        return $this->response?->status();
    }
}
