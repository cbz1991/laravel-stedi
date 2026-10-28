<?php

namespace Cabuz\Stedi\Concerns;

use Cabuz\Stedi\Support\CursorPage;

/**
 * The payer network.
 *
 * Generated from Stedi's OpenAPI spec (payers.json) by bin/generate.
 */
trait Payers
{
    /**
     * Retrieve a single payer record by its Stedi payer ID.
     *
     * @return array<string, mixed>
     */
    public function getPayerRecord(string $stediId): array
    {
        return $this->request('payers', 'GET', $this->path('/payer/%s', $stediId));
    }

    /**
     * List Stedi's supported payers in JSON format.
     *
     * @param  array<string, mixed>  $query  pageSize
     */
    public function listPayerRecords(array $query = []): CursorPage
    {
        return $this->paginate('payers', 'GET', '/payers', $query);
    }

    /**
     * List Stedi's supported payers in CSV format.
     * Returns the raw file contents.
     */
    public function listPayerRecordsCsv(): string
    {
        return $this->send('payers', 'GET', '/payers/csv')->body();
    }

    /**
     * Search for payers by name, ID, or alias.
     *
     * @param  array<string, mixed>  $query  pageSize, query, eligibilityCheck, claimStatus, professionalClaimSubmission, dentalClaimSubmission, institutionalClaimSubmission, claimPayment, coordinationOfBenefits, unsolicitedClaimAttachment, electronicFundsTransfer, claimPaymentAdviceWithElectronicFundsTransfer, coverageTypes, operatingStates, programs
     */
    public function searchPayers(array $query = []): CursorPage
    {
        return $this->paginate('payers', 'GET', '/payers/search', $query);
    }
}
