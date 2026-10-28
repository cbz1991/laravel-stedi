<?php

namespace Cabuz\Stedi\Events;

/**
 * A batch eligibility check finished (COMPLETED, COMPLETED_WITH_ERRORS or
 * VALIDATION_FAILED), as seen by the PollEligibilityBatch job. Fetch the
 * results with Stedi::getBatchItems() or Stedi::batchEligibilityPolling().
 */
class EligibilityBatchFinished
{
    /**
     * @param  array<string, mixed>  $batch  The getBatch() response: id, status, successCount, errorCount...
     */
    public function __construct(
        public readonly array $batch,
    ) {}

    public function batchId(): string
    {
        return (string) ($this->batch['id'] ?? '');
    }

    public function status(): string
    {
        return (string) ($this->batch['status'] ?? '');
    }

    /**
     * Whether every check in the batch went through.
     */
    public function successful(): bool
    {
        return $this->status() === 'COMPLETED';
    }
}
