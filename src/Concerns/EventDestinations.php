<?php

namespace Cabuz\Stedi\Concerns;

use Cabuz\Stedi\Support\CursorPage;
use Illuminate\Support\Str;

/**
 * Event destinations (webhooks) and their events.
 *
 * Generated from Stedi's OpenAPI spec (event-destinations.json) by bin/generate.
 */
trait EventDestinations
{
    /**
     * Lists all destinations configured for your account. Results are
     * paginated.
     *
     * @param  array<string, mixed>  $query  pageSize, status, eventType
     */
    public function listEventDestinations(array $query = []): CursorPage
    {
        return $this->paginate('events', 'GET', '/destinations', $query);
    }

    /**
     * Creates an event destination. Returns destination details and a signing
     * secret for verifying event payloads.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function createEventDestination(array $data, ?string $idempotencyKey = null): array
    {
        return $this->request('events', 'POST', '/destinations', [], $data, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * Deletes an existing destination. This action is irreversible. Deleting a
     * destination that is already deleted succeeds with the same response
     * (idempotent).
     *
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function deleteEventDestination(string $destinationId, ?string $idempotencyKey = null): array
    {
        return $this->request('events', 'DELETE', $this->path('/destinations/%s', $destinationId), [], null, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * Retrieves details for an existing event destination.
     *
     * @return array<string, mixed>
     */
    public function getEventDestination(string $destinationId): array
    {
        return $this->request('events', 'GET', $this->path('/destinations/%s', $destinationId));
    }

    /**
     * Updates an existing destination configuration.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function updateEventDestination(string $destinationId, array $data = [], ?string $idempotencyKey = null): array
    {
        return $this->request('events', 'POST', $this->path('/destinations/%s', $destinationId), [], $data, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * Retrieves the current signing secret for a destination. Use this secret
     * to verify the authenticity of event payloads.
     *
     * @return array<string, mixed>
     */
    public function getEventDestinationSecret(string $destinationId): array
    {
        return $this->request('events', 'GET', $this->path('/destinations/%s/secret', $destinationId));
    }

    /**
     * Rotates the signing secret for a destination. The previous secret
     * remains valid for the period specified by `previousSecretExpiryHours`
     * (or `0` for immediate invalidation) to allow for a graceful transition.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function rotateEventDestinationSecret(string $destinationId, array $data = [], ?string $idempotencyKey = null): array
    {
        return $this->request('events', 'POST', $this->path('/destinations/%s/secret/rotate', $destinationId), [], $data, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * Lists all events for your account. Results are paginated.
     *
     * @param  array<string, mixed>  $query  pageSize, eventId, status, eventType, created
     */
    public function listEventDestinationEvents(array $query = []): CursorPage
    {
        return $this->paginate('events', 'GET', '/events', $query);
    }

    /**
     * Retrieves the details of an existing event by its identifier.
     *
     * @return array<string, mixed>
     */
    public function getEventDestinationEvent(string $eventId): array
    {
        return $this->request('events', 'GET', $this->path('/events/%s', $eventId));
    }
}
