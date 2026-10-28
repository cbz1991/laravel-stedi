<?php

namespace Cabuz\Stedi\Events;

use Illuminate\Support\Str;

/**
 * A verified event from a Stedi event destination. Events are thin: they say
 * what changed (type, resource), so fetch the resource with the API.
 */
class WebhookReceived
{
    /**
     * @param  array<string, mixed>  $payload  The decoded event: id, type, resource, account, environment, created...
     * @param  string  $webhookId  The webhook-id header, the same across retries of one delivery.
     */
    public function __construct(
        public readonly array $payload,
        public readonly string $webhookId,
    ) {}

    /**
     * The event type, e.g. "enrollment.activated".
     */
    public function type(): string
    {
        return (string) ($this->payload['type'] ?? '');
    }

    /**
     * Whether the type matches a pattern, e.g. "enrollment.*".
     */
    public function is(string $pattern): bool
    {
        return Str::is($pattern, $this->type());
    }

    /**
     * The ID of the resource the event is about.
     */
    public function resourceId(): ?string
    {
        $id = $this->payload['resource']['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /**
     * The type of the resource the event is about.
     */
    public function resourceType(): ?string
    {
        $type = $this->payload['resource']['type'] ?? null;

        return is_string($type) ? $type : null;
    }

    public function isProduction(): bool
    {
        return ($this->payload['environment'] ?? null) === 'PRODUCTION';
    }
}
