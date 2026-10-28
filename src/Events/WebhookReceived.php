<?php

namespace Cabuz\Stedi\Events;

use Cabuz\Stedi\Stedi;
use Illuminate\Container\Container;
use Illuminate\Support\Str;

/**
 * A verified event from a Stedi event destination. Events are thin: they say
 * what changed (type, resource), so fetch the resource with the API.
 *
 * Every delivery fires this event, and known types also fire their own class
 * (EnrollmentActivated, TransactionProcessed, ...), which extends this one.
 */
class WebhookReceived
{
    /**
     * The event class fired for each documented Stedi event type.
     *
     * @var array<string, class-string<WebhookReceived>>
     */
    public const TYPES = [
        'event.ping' => EventPing::class,
        'enrollment.activated' => EnrollmentActivated::class,
        'enrollment.rejected' => EnrollmentRejected::class,
        'enrollment.updated' => EnrollmentUpdated::class,
        'enrollment.task.assigned' => EnrollmentTaskAssigned::class,
        'enrollment.task.completed' => EnrollmentTaskCompleted::class,
        'enrollment.task.deleted' => EnrollmentTaskDeleted::class,
        'file.processed' => FileProcessed::class,
        'file.failed' => FileFailed::class,
        'transaction.processed' => TransactionProcessed::class,
        'app.installed' => AppInstalled::class,
        'app.uninstalled' => AppUninstalled::class,
    ];

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

    /**
     * The same event as its type's own class, e.g. EnrollmentActivated, or
     * null for a type this package does not know yet.
     */
    public function typed(): ?self
    {
        $class = static::TYPES[$this->type()] ?? null;

        return $class === null ? null : new $class($this->payload, $this->webhookId);
    }

    /**
     * Fetch the resource the event is about: an enrollment, execution,
     * transaction or event destination. Null for other resource types, such as
     * enrollment tasks. Pass a client to use another API key.
     *
     * @return array<string, mixed>|null
     */
    public function fetch(?Stedi $stedi = null): ?array
    {
        $id = $this->resourceId();

        if ($id === null) {
            return null;
        }

        $stedi ??= Container::getInstance()->make(Stedi::class);

        return match ($this->resourceType()) {
            'enrollment' => $stedi->getEnrollment($id),
            'execution' => $stedi->getExecution($id),
            'transaction' => $stedi->getTransaction($id),
            'destination' => $stedi->getEventDestination($id),
            default => null,
        };
    }
}
