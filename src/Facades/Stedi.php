<?php

namespace Cabuz\Stedi\Facades;

use Cabuz\Stedi\Stedi as StediClient;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;

/**
 * @method static StediClient withApiKey(string $apiKey)
 * @method static StediClient withHeaders(array<string, mixed> $headers)
 * @method static StediClient withTimeout(int $seconds)
 * @method static StediClient withRetries(int $retries)
 * @method static StediClient withConfig(array<string, mixed> $config)
 * @method static mixed config(string $key, mixed $default = null)
 * @method static \Illuminate\Http\Client\Response|null lastResponse()
 * @method static string endpoint(string $api)
 * @method static array<string, mixed> request(string $api, string $method, string $path, array<string, mixed> $query = [], array<string, mixed>|null $data = null, ?string $idempotencyKey = null)
 * @method static \Cabuz\Stedi\Support\CursorPage paginate(string $api, string $method, string $path, array<string, mixed> $query = [])
 * @method static \Illuminate\Http\Client\Response send(string $api, string $method, string $path, array<string, mixed> $query = [], array<string, mixed>|null $data = null, ?string $idempotencyKey = null)
 * @method static \Illuminate\Http\Client\PendingRequest pendingRequest(string $method = 'GET', bool $idempotent = false)
 * @method static array<string, mixed> createClaimAttachmentFile(array<string, mixed> $data)
 * @method static array<string, mixed> submitClaimAttachmentRawX12(array<string, mixed> $data)
 * @method static \Cabuz\Stedi\Support\CursorPage listClaims(array<string, mixed> $query = [])
 * @method static array<string, mixed> getClaim(string $id)
 * @method static \Cabuz\Stedi\Support\CursorPage getClaimTimeline(string $id, array<string, mixed> $query = [])
 * @method static array<string, mixed> createProfessionalClaimSubmission(array<string, mixed> $data, ?string $idempotencyKey = null)
 * @method static array<string, mixed> validateProfessionalClaimSubmission(array<string, mixed> $data)
 * @method static array<string, mixed> getProfessionalClaimSubmission(string $id)
 * @method static array<string, mixed> retryEvent(string $eventId)
 * @method static \Cabuz\Stedi\Support\CursorPage listExecutions(array<string, mixed> $query = [])
 * @method static array<string, mixed> retryExecutions(array<string, mixed> $data)
 * @method static array<string, mixed> getExecution(string $executionId)
 * @method static \Cabuz\Stedi\Support\CursorPage listExecutionFaults(string $executionId, array<string, mixed> $query = [])
 * @method static string getExecutionInputDocument(string $executionId)
 * @method static array<string, mixed> getExecutionInputDocumentUrl(string $executionId)
 * @method static string getExecutionMetadataDocument(string $executionId)
 * @method static array<string, mixed> getExecutionMetadataDocumentUrl(string $executionId)
 * @method static string getExecutionOutputDocument(string $executionId)
 * @method static array<string, mixed> getExecutionOutputDocumentUrl(string $executionId)
 * @method static \Cabuz\Stedi\Support\CursorPage listExecutionTransactions(string $executionId, array<string, mixed> $query = [])
 * @method static array<string, mixed> createOutboundFragment(string $fragmentGroupId, array<string, mixed> $data, ?string $idempotencyKey = null)
 * @method static array<string, mixed> generateTransactionGroupEdi(string $partnershipId, string $transactionGroupId, array<string, mixed> $data = [], ?string $idempotencyKey = null)
 * @method static array<string, mixed> createTransactionGroup(string $partnershipId, string $transactionGroupId, string $transactionSettingId, array<string, mixed> $data = [], ?string $idempotencyKey = null)
 * @method static array<string, mixed> createPartnershipOutboundTransaction(string $partnershipId, string $transactionSettingId, array<string, mixed> $data, ?string $idempotencyKey = null)
 * @method static \Cabuz\Stedi\Support\CursorPage listPollingExecutions(array<string, mixed> $query = [])
 * @method static \Cabuz\Stedi\Support\CursorPage listPollingTransactions(array<string, mixed> $query = [])
 * @method static \Cabuz\Stedi\Support\CursorPage listTransactions(array<string, mixed> $query = [])
 * @method static array<string, mixed> getTransaction(string $transactionId)
 * @method static string getTransactionAttachmentDocument(string $transactionId, string $artifactId)
 * @method static array<string, mixed> getTransactionAttachmentDocumentUrl(string $transactionId, string $artifactId)
 * @method static array<string, mixed> getTransactionFragment(string $transactionId, string $fragmentIndex)
 * @method static string getTransactionFragmentOutputDocument(string $transactionId, string $fragmentIndex)
 * @method static array<string, mixed> getTransactionFragmentOutputDocumentUrl(string $transactionId, string $fragmentIndex)
 * @method static string getTransactionInputDocument(string $transactionId)
 * @method static array<string, mixed> getTransactionInputDocumentUrl(string $transactionId)
 * @method static string getTransactionOutputDocument(string $transactionId)
 * @method static array<string, mixed> getTransactionOutputDocumentUrl(string $transactionId)
 * @method static array<string, mixed> generateEdi(string $partnershipId, array<string, mixed> $data, ?string $idempotencyKey = null)
 * @method static array<string, mixed> batchEligibilityChecks(array<string, mixed> $data)
 * @method static array<string, mixed> getBatch(string $batchId)
 * @method static \Cabuz\Stedi\Support\CursorPage getBatchItems(string $batchId, array<string, mixed> $query = [])
 * @method static string getEligibilityCheckPdf(string $eligibilityCheckId)
 * @method static \Cabuz\Stedi\Support\CursorPage batchEligibilityPolling(array<string, mixed> $query = [])
 * @method static array<string, mixed> deleteEnrollmentDocument(string $documentId)
 * @method static array<string, mixed> createEnrollmentDocumentDownload(string $documentId)
 * @method static \Cabuz\Stedi\Support\CursorPage listEnrollments(array<string, mixed> $query = [])
 * @method static array<string, mixed> createEnrollment(array<string, mixed> $data)
 * @method static array<string, mixed> exportEnrollmentsCsv(array<string, mixed> $data = [])
 * @method static array<string, mixed> deleteEnrollment(string $enrollmentId)
 * @method static array<string, mixed> getEnrollment(string $enrollmentId)
 * @method static array<string, mixed> updateEnrollment(string $enrollmentId, array<string, mixed> $data)
 * @method static array<string, mixed> createEnrollmentDocumentUpload(string $enrollmentId, array<string, mixed> $data)
 * @method static \Cabuz\Stedi\Support\CursorPage listProviders(array<string, mixed> $query = [])
 * @method static array<string, mixed> createProvider(array<string, mixed> $data)
 * @method static array<string, mixed> deleteProvider(string $providerId)
 * @method static array<string, mixed> getProvider(string $providerId)
 * @method static array<string, mixed> updateProvider(string $providerId, array<string, mixed> $data)
 * @method static array<string, mixed> updateTask(string $taskId, array<string, mixed> $data = [])
 * @method static \Cabuz\Stedi\Support\CursorPage listEventDestinations(array<string, mixed> $query = [])
 * @method static array<string, mixed> createEventDestination(array<string, mixed> $data, ?string $idempotencyKey = null)
 * @method static array<string, mixed> deleteEventDestination(string $destinationId, ?string $idempotencyKey = null)
 * @method static array<string, mixed> getEventDestination(string $destinationId)
 * @method static array<string, mixed> updateEventDestination(string $destinationId, array<string, mixed> $data = [], ?string $idempotencyKey = null)
 * @method static array<string, mixed> getEventDestinationSecret(string $destinationId)
 * @method static array<string, mixed> rotateEventDestinationSecret(string $destinationId, array<string, mixed> $data = [], ?string $idempotencyKey = null)
 * @method static \Cabuz\Stedi\Support\CursorPage listEventDestinationEvents(array<string, mixed> $query = [])
 * @method static array<string, mixed> getEventDestinationEvent(string $eventId)
 * @method static array<string, mixed> claimStatus(array<string, mixed> $data)
 * @method static array<string, mixed> claimStatusRawX12(array<string, mixed> $data)
 * @method static array<string, mixed> eligibilityCheck(array<string, mixed> $data)
 * @method static array<string, mixed> eligibilityRawX12Check(array<string, mixed> $data)
 * @method static array<string, mixed> institutionalClaimsRawX12Submission(array<string, mixed> $data, ?string $idempotencyKey = null)
 * @method static array<string, mixed> institutionalClaimsSubmission(array<string, mixed> $data, ?string $idempotencyKey = null)
 * @method static array<string, mixed> claimsRawX12Submission(array<string, mixed> $data, ?string $idempotencyKey = null)
 * @method static array<string, mixed> claimsSubmission(array<string, mixed> $data, ?string $idempotencyKey = null)
 * @method static array<string, mixed> convertReport277(string $transactionId)
 * @method static array<string, mixed> convertReport835(string $transactionId)
 * @method static array<string, mixed> coordinationOfBenefits(array<string, mixed> $data)
 * @method static array<string, mixed> dentalClaimsRawX12Submission(array<string, mixed> $data, ?string $idempotencyKey = null)
 * @method static array<string, mixed> dentalClaimsSubmission(array<string, mixed> $data, ?string $idempotencyKey = null)
 * @method static string getElectronicRemittanceAdvicePdf(string $transactionId, array<string, mixed> $query = [])
 * @method static array<string, mixed> createEligibilityCheck(array<string, mixed> $data)
 * @method static array<string, mixed> exportPdf(array<string, mixed> $query = [])
 * @method static string getPdf1500(string $transactionId, array<string, mixed> $query = [])
 * @method static array<string, mixed> insuranceDiscoveryCheck(array<string, mixed> $data)
 * @method static array<string, mixed> getInsuranceDiscoveryCheck(string $discoveryId)
 * @method static array<string, mixed> getPayerRecord(string $stediId)
 * @method static \Cabuz\Stedi\Support\CursorPage listPayerRecords(array<string, mixed> $query = [])
 * @method static string listPayerRecordsCsv()
 * @method static \Cabuz\Stedi\Support\CursorPage searchPayers(array<string, mixed> $query = [])
 *
 * @see StediClient
 */
class Stedi extends Facade
{
    /**
     * Fake the Stedi APIs for tests.
     *
     * Keys are "{api}/{path}" (wildcards allowed), where api is healthcare,
     * claims, manager, payers, enrollment, events or core, e.g.
     * "healthcare/eligibility-check" or "payers/payers/search*". Values are
     * JSON arrays or Http::response() / Http::sequence() stubs. Unmatched
     * Stedi requests get an empty 200, so tests never reach Stedi. Assert on
     * requests with Http::assertSent().
     *
     * @param  array<string, array<mixed>|PromiseInterface|Closure>  $responses
     */
    public static function fake(array $responses = []): void
    {
        $app = static::getFacadeApplication();

        if ($app !== null && blank($app['config']->get('stedi.api_key'))) {
            $app['config']->set('stedi.api_key', 'test_fake-key');
            $app->forgetInstance(StediClient::class);
            static::clearResolvedInstance(StediClient::class);
        }

        /** @var StediClient $client */
        $client = static::getFacadeRoot();
        $stubs = [];

        foreach ($responses as $key => $response) {
            if (str_starts_with($key, 'http')) {
                $url = $key;
            } else {
                [$api, $path] = array_pad(explode('/', ltrim($key, '/'), 2), 2, '');
                $url = $client->endpoint($api).'/'.$path;
            }

            $stubs[$url] = is_array($response) ? Http::response($response) : $response;
        }

        $stubs['https://*.stedi.com/*'] = Http::response([]);

        Http::fake($stubs);
    }

    protected static function getFacadeAccessor(): string
    {
        return StediClient::class;
    }
}
