<?php

namespace Cabuz\Stedi\Jobs;

use Cabuz\Stedi\Events\EligibilityBatchFinished;
use Cabuz\Stedi\Stedi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Bus\Dispatcher as Bus;
use Illuminate\Contracts\Events\Dispatcher as Events;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use RuntimeException;

/**
 * Checks a batch eligibility check until it finishes, then fires
 * EligibilityBatchFinished. Each check that finds the batch still running
 * queues a new job after the interval, so no worker is blocked while Stedi
 * works through the batch.
 *
 * Encrypted on the queue, since it may carry an API key.
 */
class PollEligibilityBatch implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * Batch statuses after which nothing changes any more.
     */
    public const FINISHED = ['COMPLETED', 'COMPLETED_WITH_ERRORS', 'VALIDATION_FAILED'];

    /**
     * The Stedi batch ID. Not named $batchId, which Laravel reserves for its
     * own job batches and copies unencrypted into the queue payload.
     */
    public string $stediBatchId;

    /**
     * When to give up (Unix time).
     */
    public int $deadline;

    /**
     * @param  int  $interval  Seconds between checks.
     * @param  int  $timeout  Seconds after which the job fails instead of checking again.
     * @param  string|null  $apiKey  The key the batch was submitted with, when it is not the configured one.
     */
    public function __construct(
        string $batchId,
        public int $interval = 60,
        int $timeout = 86400,
        public ?string $apiKey = null,
    ) {
        $this->stediBatchId = $batchId;
        $this->deadline = time() + $timeout;
    }

    public function batchId(): string
    {
        return $this->stediBatchId;
    }

    public function handle(Stedi $stedi, Events $events, Bus $bus): void
    {
        if ($this->apiKey !== null) {
            $stedi = $stedi->withApiKey($this->apiKey);
        }

        $batch = $stedi->getBatch($this->stediBatchId);

        if (in_array($batch['status'] ?? null, static::FINISHED, true)) {
            $events->dispatch(new EligibilityBatchFinished($batch));

            return;
        }

        if (time() + $this->interval > $this->deadline) {
            throw new RuntimeException("Stedi eligibility batch [{$this->stediBatchId}] did not finish in time.");
        }

        $next = clone $this;
        $next->delay($this->interval);

        $bus->dispatch($next);
    }
}
