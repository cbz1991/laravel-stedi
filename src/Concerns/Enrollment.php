<?php

namespace Cabuz\Stedi\Concerns;

use Cabuz\Stedi\Support\CursorPage;

/**
 * Transaction enrollment: providers, enrollments and documents.
 *
 * Generated from Stedi's OpenAPI spec (enrollment.json) by bin/generate.
 */
trait Enrollment
{
    /**
     * Deletes the specified PDF document associated with a transaction
     * enrollment.
     *
     * @return array<string, mixed>
     */
    public function deleteEnrollmentDocument(string $documentId): array
    {
        return $this->request('enrollment', 'DELETE', $this->path('/documents/%s', $documentId));
    }

    /**
     * Returns a pre-signed URL to download the specified enrollment document.
     *
     * @return array<string, mixed>
     */
    public function createEnrollmentDocumentDownload(string $documentId): array
    {
        return $this->request('enrollment', 'GET', $this->path('/documents/%s/download', $documentId));
    }

    /**
     * Lists transaction enrollment records with optional filtering and
     * pagination.
     *
     * @param  array<string, mixed>  $query  pageSize, filter, status, providerNpis, providerTaxIds, providerNames, providerIds, payerIds, sources, transactions, createdFrom, createdTo, statusUpdatedFrom, statusUpdatedTo, importId, requestedEffectiveDateFrom, requestedEffectiveDateTo, lastEraReceivedFrom, lastEraReceivedTo, userEmails, sortBy
     */
    public function listEnrollments(array $query = []): CursorPage
    {
        return $this->paginate('enrollment', 'GET', '/enrollments', $query);
    }

    /**
     * Creates a new transaction enrollment request. Transaction enrollment
     * registers a provider to exchange specific transaction types with a
     * payer.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createEnrollment(array $data): array
    {
        return $this->request('enrollment', 'POST', '/enrollments', [], $data);
    }

    /**
     * Exports enrollment records as a CSV file with optional filtering.
     * Supports the same filtering parameters as ListEnrollments operation.
     * Returns a presigned URL to download the generated CSV file from S3.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function exportEnrollmentsCsv(array $data = []): array
    {
        return $this->request('enrollment', 'POST', '/enrollments/export', [], $data);
    }

    /**
     * Deletes an enrollment request. Only enrollments in DRAFT status can be
     * deleted. This operation is idempotent.
     *
     * @return array<string, mixed>
     */
    public function deleteEnrollment(string $enrollmentId): array
    {
        return $this->request('enrollment', 'DELETE', $this->path('/enrollments/%s', $enrollmentId));
    }

    /**
     * Retrieves an enrollment request by its ID. This operation returns the
     * complete enrollment details including provider and payer information.
     *
     * @return array<string, mixed>
     */
    public function getEnrollment(string $enrollmentId): array
    {
        return $this->request('enrollment', 'GET', $this->path('/enrollments/%s', $enrollmentId));
    }

    /**
     * Updates an existing enrollment request. Only enrollments in DRAFT status
     * can be updated. Once an enrollment is submitted, it cannot be modified.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateEnrollment(string $enrollmentId, array $data): array
    {
        return $this->request('enrollment', 'POST', $this->path('/enrollments/%s', $enrollmentId), [], $data);
    }

    /**
     * Returns a pre-signed URL to upload a PDF document for the specified
     * transaction enrollment.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createEnrollmentDocumentUpload(string $enrollmentId, array $data): array
    {
        return $this->request('enrollment', 'POST', $this->path('/enrollments/%s/documents', $enrollmentId), [], $data);
    }

    /**
     * Lists providers with optional filtering and pagination.
     *
     * @param  array<string, mixed>  $query  pageSize, filter, providerNpis, providerTaxIds, sortBy
     */
    public function listProviders(array $query = []): CursorPage
    {
        return $this->paginate('enrollment', 'GET', '/providers', $query);
    }

    /**
     * Creates a new provider record. Providers must be created before they can
     * be enrolled with payers.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createProvider(array $data): array
    {
        return $this->request('enrollment', 'POST', '/providers', [], $data);
    }

    /**
     * Deletes a provider record. Providers can only be deleted if they have no
     * associated enrollments. This operation is idempotent.
     *
     * @return array<string, mixed>
     */
    public function deleteProvider(string $providerId): array
    {
        return $this->request('enrollment', 'DELETE', $this->path('/providers/%s', $providerId));
    }

    /**
     * Retrieves a provider record by its ID. This operation returns the
     * complete provider details including contact information.
     *
     * @return array<string, mixed>
     */
    public function getProvider(string $providerId): array
    {
        return $this->request('enrollment', 'GET', $this->path('/providers/%s', $providerId));
    }

    /**
     * Updates an existing provider's information. Note that NPI and tax ID
     * cannot be changed after creation.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateProvider(string $providerId, array $data): array
    {
        return $this->request('enrollment', 'POST', $this->path('/providers/%s', $providerId), [], $data);
    }

    /**
     * Updates a task associated with an enrollment.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function updateTask(string $taskId, array $data = []): array
    {
        return $this->request('enrollment', 'POST', $this->path('/tasks/%s', $taskId), [], $data);
    }
}
