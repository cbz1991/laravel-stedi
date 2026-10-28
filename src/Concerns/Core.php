<?php

namespace Cabuz\Stedi\Concerns;

use Cabuz\Stedi\Support\CursorPage;
use Illuminate\Support\Str;

/**
 * Stedi Core (EDI platform): transactions, executions, partnerships and EDI generation.
 *
 * Generated from Stedi's OpenAPI spec (core.json) by bin/generate.
 */
trait Core
{
    /**
     * This endpoint retriggers the specified processing event.
     *
     * @return array<string, mixed>
     */
    public function retryEvent(string $eventId): array
    {
        return $this->request('core', 'POST', $this->path('/events/%s/retry', $eventId));
    }

    /**
     * Fetch a list of executions, sorted by the date they were created from
     * newest to oldest.
     *
     * @param  array<string, mixed>  $query  pageSize, direction, faultCode, partnershipId, status, mode, from, to
     */
    public function listExecutions(array $query = []): CursorPage
    {
        return $this->paginate('core', 'GET', '/executions', $query);
    }

    /**
     * Queue file executions to be retried.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function retryExecutions(array $data): array
    {
        return $this->request('core', 'POST', '/executions/retry', [], $data);
    }

    /**
     * Retrieve the file execution details for a given `executionId`.
     *
     * @return array<string, mixed>
     */
    public function getExecution(string $executionId): array
    {
        return $this->request('core', 'GET', $this->path('/executions/%s', $executionId));
    }

    /**
     * Fetch a list of faults for a given file execution, sorted by the date
     * they were created from newest to oldest.
     *
     * @param  array<string, mixed>  $query  pageSize
     */
    public function listExecutionFaults(string $executionId, array $query = []): CursorPage
    {
        return $this->paginate('core', 'GET', $this->path('/executions/%s/faults', $executionId), $query);
    }

    /**
     * Fetch a file execution's input document before any translation and
     * mappings.
     * Returns the raw file contents.
     */
    public function getExecutionInputDocument(string $executionId): string
    {
        return $this->send('core', 'GET', $this->path('/executions/%s/input', $executionId))->body();
    }

    /**
     * This endpoint is used to retrieve an execution's input document before
     * it passes through any translation and mappings. Generally, this will be
     * EDI. This endpoint returns the document download URL, it must be then
     * fetched for the actual document content. There are no size restrictions
     * on documents when fetching from this endpoint.
     *
     * @return array<string, mixed>
     */
    public function getExecutionInputDocumentUrl(string $executionId): array
    {
        return $this->request('core', 'GET', $this->path('/executions/%s/input-url', $executionId));
    }

    /**
     * Fetch a file execution's metadata document.
     * Returns the raw file contents.
     */
    public function getExecutionMetadataDocument(string $executionId): string
    {
        return $this->send('core', 'GET', $this->path('/executions/%s/metadata', $executionId))->body();
    }

    /**
     * This endpoint is used to retrieve a file execution's metadata document,
     * limited to inbound/outbound x12 files. This endpoint returns the
     * metadata document download URL, it must be then fetched for the actual
     * document content. There are no size restrictions on documents when
     * fetching from this endpoint.
     *
     * @return array<string, mixed>
     */
    public function getExecutionMetadataDocumentUrl(string $executionId): array
    {
        return $this->request('core', 'GET', $this->path('/executions/%s/metadata-url', $executionId));
    }

    /**
     * Fetch a file execution's output document. Only available for outbound
     * files.
     * Returns the raw file contents.
     */
    public function getExecutionOutputDocument(string $executionId): string
    {
        return $this->send('core', 'GET', $this->path('/executions/%s/output', $executionId))->body();
    }

    /**
     * This endpoint is used to retrieve an execution's output document. This
     * endpoint returns the document download URL, it must be then fetched for
     * the actual document content. There are no size restrictions on documents
     * when fetching from this endpoint.
     *
     * @return array<string, mixed>
     */
    public function getExecutionOutputDocumentUrl(string $executionId): array
    {
        return $this->request('core', 'GET', $this->path('/executions/%s/output-url', $executionId));
    }

    /**
     * Fetch a list of transactions for a file execution, including the full
     * transaction details.
     *
     * @param  array<string, mixed>  $query  pageSize
     */
    public function listExecutionTransactions(string $executionId, array $query = []): CursorPage
    {
        return $this->paginate('core', 'GET', $this->path('/executions/%s/transactions', $executionId), $query);
    }

    /**
     * This endpoint stages a fragment for outbound delivery.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function createOutboundFragment(string $fragmentGroupId, array $data, ?string $idempotencyKey = null): array
    {
        return $this->request('core', 'POST', $this->path('/fragments/%s', $fragmentGroupId), [], $data, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * This endpoint generates and delivers a fully-formed EDI file containing
     * the staged transactions in the specified transaction group.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function generateTransactionGroupEdi(string $partnershipId, string $transactionGroupId, array $data = [], ?string $idempotencyKey = null): array
    {
        return $this->request('core', 'POST', $this->path('/partnerships/%s/transaction-groups/%s/generate-edi', $partnershipId, $transactionGroupId), [], $data, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * This endpoint stages a transaction for outbound delivery.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function createTransactionGroup(string $partnershipId, string $transactionGroupId, string $transactionSettingId, array $data = [], ?string $idempotencyKey = null): array
    {
        return $this->request('core', 'POST', $this->path('/partnerships/%s/transaction-groups/%s/transactions/%s', $partnershipId, $transactionGroupId, $transactionSettingId), [], $data, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * This endpoint generates and delivers fully-formed EDI files containing a
     * single transaction. It is the simplest way to generate EDI with Stedi.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function createPartnershipOutboundTransaction(string $partnershipId, string $transactionSettingId, array $data, ?string $idempotencyKey = null): array
    {
        return $this->request('core', 'POST', $this->path('/partnerships/%s/transactions/%s', $partnershipId, $transactionSettingId), [], $data, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * Poll for new file executions that Stedi has recently processed.
     *
     * @param  array<string, mixed>  $query  pageSize, startDateTime
     */
    public function listPollingExecutions(array $query = []): CursorPage
    {
        return $this->paginate('core', 'GET', '/polling/executions', $query);
    }

    /**
     * Poll for new transactions that Stedi has processed.
     *
     * @param  array<string, mixed>  $query  pageSize, startDateTime
     */
    public function listPollingTransactions(array $query = []): CursorPage
    {
        return $this->paginate('core', 'GET', '/polling/transactions', $query);
    }

    /**
     * Fetch a list of transactions, sorted by the date they were created from
     * newest to oldest.
     *
     * @param  array<string, mixed>  $query  pageSize, businessIdentifier, transactionSetId, sender, receiver, direction, mode, status, from, to, elementId, partnershipId
     */
    public function listTransactions(array $query = []): CursorPage
    {
        return $this->paginate('core', 'GET', '/transactions', $query);
    }

    /**
     * Fetch details for a specific transaction.
     *
     * @return array<string, mixed>
     */
    public function getTransaction(string $transactionId): array
    {
        return $this->request('core', 'GET', $this->path('/transactions/%s', $transactionId));
    }

    /**
     * Retrieve a transaction's output document.
     * Returns the raw file contents.
     */
    public function getTransactionAttachmentDocument(string $transactionId, string $artifactId): string
    {
        return $this->send('core', 'GET', $this->path('/transactions/%s/attachment/%s', $transactionId, $artifactId))->body();
    }

    /**
     * This endpoint is used to retrieve a transaction's attachment document
     * url.
     *
     * @return array<string, mixed>
     */
    public function getTransactionAttachmentDocumentUrl(string $transactionId, string $artifactId): array
    {
        return $this->request('core', 'GET', $this->path('/transactions/%s/attachment/%s/url', $transactionId, $artifactId));
    }

    /**
     * Retrieve metadata for a fragment.
     *
     * @return array<string, mixed>
     */
    public function getTransactionFragment(string $transactionId, string $fragmentIndex): array
    {
        return $this->request('core', 'GET', $this->path('/transactions/%s/fragments/%s', $transactionId, $fragmentIndex));
    }

    /**
     * Retrieve a transaction fragment's output document after it has been
     * translated.
     * Returns the raw file contents.
     */
    public function getTransactionFragmentOutputDocument(string $transactionId, string $fragmentIndex): string
    {
        return $this->send('core', 'GET', $this->path('/transactions/%s/fragments/%s/output', $transactionId, $fragmentIndex))->body();
    }

    /**
     * This endpoint is used to retrieve a transaction fragment's output
     * document URL after it has been translated via Core.
     *
     * @return array<string, mixed>
     */
    public function getTransactionFragmentOutputDocumentUrl(string $transactionId, string $fragmentIndex): array
    {
        return $this->request('core', 'GET', $this->path('/transactions/%s/fragments/%s/output-url', $transactionId, $fragmentIndex));
    }

    /**
     * Retrieve a transaction's input document.
     * Returns the raw file contents.
     */
    public function getTransactionInputDocument(string $transactionId): string
    {
        return $this->send('core', 'GET', $this->path('/transactions/%s/input', $transactionId))->body();
    }

    /**
     * This endpoint is used to retrieve a transaction's input document url
     * before it passes through any translation and mappings, generally this
     * will be EDI.
     *
     * @return array<string, mixed>
     */
    public function getTransactionInputDocumentUrl(string $transactionId): array
    {
        return $this->request('core', 'GET', $this->path('/transactions/%s/input-url', $transactionId));
    }

    /**
     * Retrieve a transaction's output document.
     * Returns the raw file contents.
     */
    public function getTransactionOutputDocument(string $transactionId): string
    {
        return $this->send('core', 'GET', $this->path('/transactions/%s/output', $transactionId))->body();
    }

    /**
     * This endpoint is used to retrieve a transaction's output document url
     * after it has been translated via Core.
     *
     * @return array<string, mixed>
     */
    public function getTransactionOutputDocumentUrl(string $transactionId): array
    {
        return $this->request('core', 'GET', $this->path('/transactions/%s/output-url', $transactionId));
    }

    /**
     * This endpoint generates and delivers fully-formed EDI files to your
     * trading partners.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function generateEdi(string $partnershipId, array $data, ?string $idempotencyKey = null): array
    {
        return $this->request('core', 'POST', $this->path('/x12/partnerships/%s/generate-edi', $partnershipId), [], $data, $idempotencyKey ?? (string) Str::uuid());
    }
}
