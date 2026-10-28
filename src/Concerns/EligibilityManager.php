<?php

namespace Cabuz\Stedi\Concerns;

use Cabuz\Stedi\Support\CursorPage;

/**
 * Batch eligibility checks and eligibility PDFs.
 *
 * Generated from Stedi's OpenAPI spec (manager.json) by bin/generate.
 */
trait EligibilityManager
{
    /**
     * Submit multiple eligibility checks for Stedi to process asynchronously.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function batchEligibilityChecks(array $data): array
    {
        return $this->request('manager', 'POST', '/eligibility-manager/batch-eligibility', [], $data);
    }

    /**
     * Retrieve the status of an eligibility check batch submitted through the
     * API or CSV upload.
     *
     * @return array<string, mixed>
     */
    public function getBatch(string $batchId): array
    {
        return $this->request('manager', 'GET', $this->path('/eligibility-manager/batch/%s', $batchId));
    }

    /**
     * Retrieve status information for all eligibility checks within a batch,
     * regardless of processing status.
     *
     * @param  array<string, mixed>  $query  pageSize, state, eligibilityCheckResult
     */
    public function getBatchItems(string $batchId, array $query = []): CursorPage
    {
        return $this->paginate('manager', 'GET', $this->path('/eligibility-manager/batch/%s/items', $batchId), $query);
    }

    /**
     * Retrieve a PDF version of the 271 benefits response for the specified
     * eligibility check.
     * Returns the raw file contents.
     */
    public function getEligibilityCheckPdf(string $eligibilityCheckId): string
    {
        return $this->send('manager', 'GET', $this->path('/eligibility-manager/eligibility-checks/%s/pdf', $eligibilityCheckId))->body();
    }

    /**
     * Retrieve batch eligibility check results.
     *
     * @param  array<string, mixed>  $query  pageSize, batchId, startDateTime
     */
    public function batchEligibilityPolling(array $query = []): CursorPage
    {
        return $this->paginate('manager', 'GET', '/eligibility-manager/polling/batch-eligibility', $query);
    }
}
