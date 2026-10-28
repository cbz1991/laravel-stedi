---
name: laravel-stedi
description: "Use when writing Laravel code that calls Stedi's healthcare APIs with cabuz/laravel-stedi: eligibility checks, claims, claim status, remittances (ERA/835), payers, enrollment, event destination webhooks, errors and tests."
---

# Laravel Stedi

`cabuz/laravel-stedi` is a Laravel SDK for Stedi's healthcare clearinghouse APIs. Every operation is a method on one client, named after Stedi's operation ID like the official TypeScript/Python SDK. The exact signature and endpoint of every method is in `vendor/cabuz/laravel-stedi/api.md`; field-level request and response shapes are in the Stedi API reference (stedi.com/docs). Look them up instead of guessing.

## Setup

```dotenv
STEDI_API_KEY=your-key
STEDI_WEBHOOK_SECRET=whsec_...   # only to receive webhooks
```

`php artisan stedi:check` checks the key; `php artisan stedi:payers "aetna"` looks up payer IDs. `Stedi::withApiKey($key)` returns a copy with another key (one per tenant). Test keys (`test_...`) only work with Stedi's mock requests, and some APIs (enrollment, batch eligibility, claim validation) reject them with `PermissionDeniedException`.

## Conventions

- Request bodies and filters are arrays; responses are decoded JSON arrays.
- List methods return `Cabuz\Stedi\Support\CursorPage`: iterate the current page, `nextPage()`, or `lazy()` for every item. Array filters are sent as repeated parameters.
- Downloads (`getElectronicRemittanceAdvicePdf`, `getEligibilityCheckPdf`, `listPayerRecordsCsv`, ...) return the raw contents as a string.

## Eligibility

```php
use Cabuz\Stedi\Facades\Stedi;

$response = Stedi::createEligibilityCheck([
    'payerId' => '60054',
    'provider' => ['name' => ['organization' => 'Acme Clinic'], 'npi' => '1999999984'],
    'subscriber' => [
        'name' => ['person' => ['firstName' => 'Jane', 'lastName' => 'Doe']],
        'memberId' => 'AETNA12345',
        'dateOfBirth' => '2004-04-04',
    ],
    'encounter' => ['services' => [['system' => 'STC', 'value' => '30']]],
]);
```

Find payer IDs with `Stedi::searchPayers(['query' => 'blue cross'])`. Other checks: `eligibilityCheck()` (v3 format), `coordinationOfBenefits()`, `insuranceDiscoveryCheck()`, and batches with `batchEligibilityChecks()` + `getBatchItems($batchId)`.

## Claims

Submit with `claimsSubmission()` (837P), `institutionalClaimsSubmission()`, `dentalClaimsSubmission()` or `createProfessionalClaimSubmission()` (CMS-1500 JSON). An idempotency key is generated automatically so automatic retries never duplicate a claim; pass `idempotencyKey: "invoice-{$invoice->id}"` to make your own retries safe. Follow up with `claimStatus()`, `listClaims(['status' => ['REJECTED']])`, `getClaimTimeline($claimId)`, `convertReport835($transactionId)` and `getElectronicRemittanceAdvicePdf($transactionId)`.

## Webhooks

The package registers `POST /stedi/webhook` (`STEDI_WEBHOOK_PATH`), verifies Stedi's Standard Webhooks signature with `STEDI_WEBHOOK_SECRET`, ignores redeliveries and fires `Cabuz\Stedi\Events\WebhookReceived`:

```php
public function handle(WebhookReceived $event): void
{
    if ($event->is('enrollment.*')) {
        $enrollment = Stedi::getEnrollment($event->resourceId());
    }
}
```

Events only carry `type()`, `resourceId()` and `resourceType()`; fetch the resource. Don't build another webhook route or verify signatures by hand.

## Errors

Every exception extends `Cabuz\Stedi\Exceptions\StediException` (`status()`, `error()`, `errorCode()`, `fieldErrors()`, `$response`): `InvalidRequestException` (400, 413, 422), `AuthenticationException` (401), `PermissionDeniedException` (403), `NotFoundException` (404), `ConflictException` (409), `RateLimitException` (429), `ServerException` (5xx), `ConnectionException`, `MissingApiKeyException`.

## Patient data

Requests and responses contain protected health information. Never log, dump or cache them in plain text, and never put real patient data in tests, fixtures or examples; use Stedi's mock values (like the ones above).

## Tests

```php
Stedi::fake([
    'healthcare/eligibility-check' => ['plans' => []],
    'payers/payers/search*' => ['items' => [], 'nextPageToken' => null],
]);

Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/eligibility-check'));
```

Keys are `{api}/{path}` with api `healthcare`, `claims`, `manager`, `payers`, `enrollment`, `events` or `core`. Unmatched Stedi requests get an empty 200. Sign webhook test requests with `Cabuz\Stedi\Webhooks\WebhookSignature::sign($body, $id, $timestamp, $secret)`.
