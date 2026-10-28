<?php

namespace Cabuz\Stedi\Concerns;

use Illuminate\Support\Str;

/**
 * Eligibility, claims, claim status, remittances, coordination of benefits and insurance discovery.
 *
 * Generated from Stedi's OpenAPI spec (healthcare.json) by bin/generate.
 */
trait Healthcare
{
    /**
     * Submit a 276/277 real-time claim status check in JSON format.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function claimStatus(array $data): array
    {
        return $this->request('healthcare', 'POST', '/change/medicalnetwork/claimstatus/v2', [], $data);
    }

    /**
     * Submit a 276/277 real-time claim status check in raw X12 EDI format.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function claimStatusRawX12(array $data): array
    {
        return $this->request('healthcare', 'POST', '/change/medicalnetwork/claimstatus/v2/raw-x12', [], $data);
    }

    /**
     * Submit a real-time 270/271 eligibility check in JSON format.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function eligibilityCheck(array $data): array
    {
        return $this->request('healthcare', 'POST', '/change/medicalnetwork/eligibility/v3', [], $data);
    }

    /**
     * Submit a real-time 270/271 eligibility check in raw X12 EDI format.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function eligibilityRawX12Check(array $data): array
    {
        return $this->request('healthcare', 'POST', '/change/medicalnetwork/eligibility/v3/raw-x12', [], $data);
    }

    /**
     * Submit an 837I institutional claim in raw X12 EDI format.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function institutionalClaimsRawX12Submission(array $data, ?string $idempotencyKey = null): array
    {
        return $this->request('healthcare', 'POST', '/change/medicalnetwork/institutionalclaims/v1/raw-x12-submission', [], $data, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * Submit an 837I institutional claim in JSON format.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function institutionalClaimsSubmission(array $data, ?string $idempotencyKey = null): array
    {
        return $this->request('healthcare', 'POST', '/change/medicalnetwork/institutionalclaims/v1/submission', [], $data, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * Submit an 837P professional claim in raw X12 EDI format.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function claimsRawX12Submission(array $data, ?string $idempotencyKey = null): array
    {
        return $this->request('healthcare', 'POST', '/change/medicalnetwork/professionalclaims/v3/raw-x12-submission', [], $data, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * Submit an 837P professional claim in JSON format.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function claimsSubmission(array $data, ?string $idempotencyKey = null): array
    {
        return $this->request('healthcare', 'POST', '/change/medicalnetwork/professionalclaims/v3/submission', [], $data, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * Retrieve a 277CA claim acknowledgment in JSON format.
     *
     * @return array<string, mixed>
     */
    public function convertReport277(string $transactionId): array
    {
        return $this->request('healthcare', 'GET', $this->path('/change/medicalnetwork/reports/v2/%s/277', $transactionId));
    }

    /**
     * Retrieve an 835 Electronic Remittance Advice (ERA) in JSON format.
     *
     * @return array<string, mixed>
     */
    public function convertReport835(string $transactionId): array
    {
        return $this->request('healthcare', 'GET', $this->path('/change/medicalnetwork/reports/v2/%s/835', $transactionId));
    }

    /**
     * Submit a coordination of benefits (COB) check in JSON format.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function coordinationOfBenefits(array $data): array
    {
        return $this->request('healthcare', 'POST', '/coordination-of-benefits', [], $data);
    }

    /**
     * Submit an 837D dental claim in raw X12 EDI format.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function dentalClaimsRawX12Submission(array $data, ?string $idempotencyKey = null): array
    {
        return $this->request('healthcare', 'POST', '/dental-claims/raw-x12-submission', [], $data, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * Submit an 837D dental claim in JSON format.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Generated when omitted, so retries never submit twice.
     * @return array<string, mixed>
     */
    public function dentalClaimsSubmission(array $data, ?string $idempotencyKey = null): array
    {
        return $this->request('healthcare', 'POST', '/dental-claims/submission', [], $data, $idempotencyKey ?? (string) Str::uuid());
    }

    /**
     * Retrieve the generated PDF of an 835 Electronic Remittance Advice (ERA).
     * Returns the raw file contents.
     *
     * @param  array<string, mixed>  $query  logo
     */
    public function getElectronicRemittanceAdvicePdf(string $transactionId, array $query = []): string
    {
        return $this->send('healthcare', 'GET', $this->path('/electronic-remittance-advice/%s/pdf', $transactionId), $query)->body();
    }

    /**
     * Submit a real-time 270/271 eligibility check in JSON format.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function createEligibilityCheck(array $data): array
    {
        return $this->request('healthcare', 'POST', '/eligibility-check', [], $data);
    }

    /**
     * Retrieve a Stedi generatedCMS-1500 Claim Form PDF for a submitted 837P
     * (professional) claim by business identifier.
     *
     * @param  array<string, mixed>  $query  businessId, background
     * @return array<string, mixed>
     */
    public function exportPdf(array $query = []): array
    {
        return $this->request('healthcare', 'GET', '/export/pdf', $query);
    }

    /**
     * Retrieve the generated CMS-1500 Claim Form PDF for a submitted 837P
     * professional claim.
     * Returns the raw file contents.
     *
     * @param  array<string, mixed>  $query  background
     */
    public function getPdf1500(string $transactionId, array $query = []): string
    {
        return $this->send('healthcare', 'GET', $this->path('/export/%s/1500/pdf', $transactionId), $query)->body();
    }

    /**
     * Submit an insurance discovery check in JSON format.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function insuranceDiscoveryCheck(array $data): array
    {
        return $this->request('healthcare', 'POST', '/insurance-discovery/check/v1', [], $data);
    }

    /**
     * Retrieve insurance discovery check results by `discoveryId`.
     *
     * @return array<string, mixed>
     */
    public function getInsuranceDiscoveryCheck(string $discoveryId): array
    {
        return $this->request('healthcare', 'GET', $this->path('/insurance-discovery/check/v1/%s', $discoveryId));
    }
}
