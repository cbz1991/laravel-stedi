<?php

namespace Cabuz\Stedi\Concerns;

use Cabuz\Stedi\Support\CursorPage;
use Illuminate\Support\Str;

/**
 * Claim records, timelines, professional claim submissions and claim attachments.
 *
 * Generated from Stedi's OpenAPI spec (claims.json) by bin/generate.
 */
trait Claims
{
    /**
     * Generate a pre-signed URL to upload a 275 claim attachment file.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createClaimAttachmentFile(array $data): array
    {
        return $this->request('claims', 'POST', '/claim-attachments/file', [], $data);
    }

    /**
     * Submit a 275 claim attachment in X12 EDI format.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function submitClaimAttachmentRawX12(array $data): array
    {
        return $this->request('claims', 'POST', '/claim-attachments/raw-x12-submission', [], $data);
    }

    /**
     * Retrieve a paginated list of claim records, newest first. Filter by
     * status, patient control numbers, or submission time.
     *
     * @param  array<string, mixed>  $query  pageSize, status, patientControlNumbers, submittedAfter, submittedBefore
     */
    public function listClaims(array $query = []): CursorPage
    {
        return $this->paginate('claims', 'GET', '/claims', $query);
    }

    /**
     * Retrieve summary information for a claim, including current processing
     * status and key details from its most recent submission.
     *
     * @return array<string, mixed>
     */
    public function getClaim(string $id): array
    {
        return $this->request('claims', 'GET', $this->path('/claims/%s', $id));
    }

    /**
     * Retrieve a paginated list of a claim's timeline entries, newest first.
     * Timeline entries include submissions, acknowledgments, and claim payment
     * information.
     *
     * @param  array<string, mixed>  $query  pageSize
     */
    public function getClaimTimeline(string $id, array $query = []): CursorPage
    {
        return $this->paginate('claims', 'GET', $this->path('/claims/%s/timeline', $id), $query);
    }

    /**
     * Submit a professional claim in JSON modeled after the CMS-1500 form
     * structure.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function createProfessionalClaimSubmission(array $data, ?string $idempotencyKey = null): array
    {
        return $this->request('claims', 'POST', '/professional-claim-submissions', [], $data, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * Validate a professional claim in the Stedi JSON format without
     * submitting it.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validateProfessionalClaimSubmission(array $data): array
    {
        return $this->request('claims', 'POST', '/professional-claim-submissions/validate', [], $data);
    }

    /**
     * Retrieve a claim's data and map it to Stedi's CMS-1500 JSON format.
     *
     * @return array<string, mixed>
     */
    public function getProfessionalClaimSubmission(string $id): array
    {
        return $this->request('claims', 'GET', $this->path('/professional-claim-submissions/%s', $id));
    }
}
