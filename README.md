# laravel-stedi

[![tests](https://github.com/cbz1991/laravel-stedi/actions/workflows/tests.yml/badge.svg)](https://github.com/cbz1991/laravel-stedi/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/cabuz/laravel-stedi)](https://packagist.org/packages/cabuz/laravel-stedi)
[![Total Downloads](https://img.shields.io/packagist/dt/cabuz/laravel-stedi)](https://packagist.org/packages/cabuz/laravel-stedi)
[![License](https://img.shields.io/packagist/l/cabuz/laravel-stedi)](LICENSE)

A Laravel SDK for the [Stedi](https://www.stedi.com) healthcare APIs.

Stedi is a healthcare clearinghouse with a JSON API: check a patient's
insurance in real time, submit claims, track their status, receive
remittances and enroll providers with payers. This package gives your Laravel
app all of it:

```php
use Cabuz\Stedi\Facades\Stedi;

$eligibility = Stedi::createEligibilityCheck([
    'payerId' => '60054', // Aetna
    'provider' => ['name' => ['organization' => 'Acme Clinic'], 'npi' => '1999999984'],
    'subscriber' => [
        'name' => ['person' => ['firstName' => 'Jane', 'lastName' => 'Doe']],
        'memberId' => 'AETNA12345',
        'dateOfBirth' => '2004-04-04',
    ],
    'encounter' => ['services' => [['system' => 'STC', 'value' => '30']]],
]);

foreach ($eligibility['plans'] as $plan) { /* coverage, benefits... */ }
```

- **Every public Stedi API**: eligibility (real-time and batch), claims
  (professional, institutional, dental, attachments), claim status,
  remittances, coordination of benefits, insurance discovery, payers,
  enrollment, event destinations and Stedi Core. A test checks every method
  against Stedi's OpenAPI specs.
- **Same names as the official SDK**: one client, one method per operation
  (`createEligibilityCheck`, `listClaims`, ...), routed to the right Stedi
  host. [api.md](api.md) lists them all.
- **Webhooks built in**: a route that verifies Stedi's signatures, drops
  redeliveries and fires a `WebhookReceived` event.
- **Safe retries**: claim submissions get an idempotency key automatically,
  so a retried request never submits a claim twice.
- **Patient data stays out of logs**: optional logging records method, path,
  status and duration only.
- **Laravel-native**: config file, facade, Laravel's HTTP client, so
  `Http::fake()` and `Stedi::fake()` work in your tests.

> This is a community package, not an official Stedi SDK. Stedi's own SDKs
> are TypeScript and Python only.

## Requirements

- PHP 8.1+
- Laravel 10.10+, 11, 12 or 13
- A Stedi account and API key

## Installation

```bash
composer require cabuz/laravel-stedi
```

Create an API key in the Stedi portal under **Settings > API Keys** and add it
to `.env`. A test key (`test_...`) answers [mock requests](https://www.stedi.com/docs/healthcare/api-reference/mock-requests-eligibility-checks)
without contacting payers.

```dotenv
STEDI_API_KEY=your-key
```

Check that it works:

```bash
php artisan stedi:check
```

To change the defaults, publish the config file:

```bash
php artisan vendor:publish --tag=stedi-config
```

## Usage

Call the operations on the `Stedi` facade, or inject `Cabuz\Stedi\Stedi`.
Method names are Stedi's operation names, the same as in the official
[TypeScript and Python SDKs](https://github.com/Stedi/sdk), and every Stedi
API is reached through the same client. Requests take arrays shaped like the
[Stedi API reference](https://www.stedi.com/docs); responses are the decoded
JSON as arrays, list methods return a [`CursorPage`](#pagination), and file
downloads (PDFs, CSVs, documents) return the raw contents.

### Find a payer

```php
$payers = Stedi::searchPayers(['query' => 'blue cross texas']);

foreach ($payers as $result) {
    $result['payer']['displayName'];
    $result['payer']['primaryPayerId']; // use as tradingPartnerServiceId
}

Stedi::getPayerRecord('HPQRS');
```

From the terminal: `php artisan stedi:payers "blue cross"`.

### Check eligibility

`checkEligibility()` runs a real-time check and answers the usual questions
without digging through the response:

```php
$eligibility = Stedi::checkEligibility([...]);  // same body as createEligibilityCheck()

if ($eligibility->hasErrors()) {
    $eligibility->errors();  // the payer's AAA rejections: code, description, followupAction
}

$eligibility->isActive();                          // active coverage for any service
$eligibility->isActive('98');                      // ... for a Service Type Code
$eligibility->copay('98', 'IN_NETWORK');           // "25"
$eligibility->coinsurance('30', 'IN_NETWORK');     // "0.2" (20%)
$eligibility->deductible(network: 'IN_NETWORK');   // "1500" for the year
$eligibility->deductibleRemaining(network: 'IN_NETWORK', level: 'INDIVIDUAL');
$eligibility->outOfPocket();
$eligibility->outOfPocketRemaining();

$eligibility->benefits('limitations', service: '33');  // any category, filtered, as a collection
$eligibility['plans'];                                 // the raw response is still there
```

A payer's AAA rejection (for example code `75`, subscriber not found) comes
back from Stedi as a `400` with the usual body. `checkEligibility()` returns
it like any other answer, with the rejection in `errors()`; it only throws
`InvalidRequestException` when Stedi rejects the request before it reaches the
payer.

The filters are optional: `service` (an STC code), `network` (`IN_NETWORK` or
`OUT_OF_NETWORK`; entries for both networks match either) and `level`
(`INDIVIDUAL`, `FAMILY`, ...). Amounts come back as strings, exactly as the
payer sent them, or `null` when the payer sent none. Wrap a response you
already have with `EligibilityResponse::from($array)`.

The other eligibility endpoints return arrays:

```php
$response = Stedi::createEligibilityCheck([...]);  // JSON eligibility check
Stedi::eligibilityCheck([...]);                    // the v3 (Change Healthcare-compatible) endpoint
Stedi::eligibilityRawX12Check(['x12' => '...']);   // raw X12 270
Stedi::coordinationOfBenefits([...]);
Stedi::insuranceDiscoveryCheck([...]);
```

Batch checks run in the background. Queue `PollEligibilityBatch` to learn
when one finishes; it checks the batch every minute (without holding a worker
in between) and fires `EligibilityBatchFinished`:

```php
use Cabuz\Stedi\Events\EligibilityBatchFinished;
use Cabuz\Stedi\Jobs\PollEligibilityBatch;

$batch = Stedi::batchEligibilityChecks(['items' => [...]]);
dispatch(new PollEligibilityBatch($batch['batchId']));  // interval: 60, timeout: 86400, apiKey: null

Event::listen(function (EligibilityBatchFinished $event) {
    $event->status();  // COMPLETED, COMPLETED_WITH_ERRORS or VALIDATION_FAILED
    Stedi::getBatchItems($event->batchId())->lazy()->each(fn ($item) => ...);
});
```

Pass `apiKey:` when the batch was submitted with another key; the job is
encrypted on the queue.

### Submit claims

```php
$claim = Stedi::claimsSubmission([...]);              // professional (837P)
Stedi::institutionalClaimsSubmission([...]);          // institutional (837I)
Stedi::dentalClaimsSubmission([...]);                 // dental (837D)
Stedi::createProfessionalClaimSubmission([...]);      // CMS-1500-shaped JSON, like the official SDK
```

Submissions send an `Idempotency-Key` header. When you don't pass one, the
package generates it, so the automatic retry after a timeout or a 5xx reuses
it and Stedi doesn't create a second claim. Pass your own (e.g. derived from
your invoice ID) to make your *own* retries safe too:

```php
Stedi::claimsSubmission($claim, idempotencyKey: "invoice-{$invoice->id}");
```

Then follow the claim:

```php
Stedi::claimStatus([...]);                              // real-time 276/277
Stedi::listClaims(['status' => ['REJECTED']]);
Stedi::getClaimTimeline($claimId)->lazy()->all();
Stedi::convertReport835($transactionId);                // remittance (ERA) as JSON
$pdf = Stedi::getElectronicRemittanceAdvicePdf($transactionId);
```

### Enrollment, event destinations and Core

Every other operation follows the same pattern, for example
`Stedi::createEnrollment([...])`, `Stedi::listProviders()`,
`Stedi::createEventDestination([...])` or `Stedi::listTransactions()`. See
[api.md](api.md) for the full list with the endpoint each method calls.

For anything else, the raw methods take the API name and path:

```php
Stedi::request('healthcare', 'POST', '/some/new/endpoint', data: ['...' => '...']);
Stedi::send('payers', 'GET', '/payers/csv')->body();
```

### Pagination

List methods return a `CursorPage`:

```php
$page = Stedi::listClaims(['status' => ['REJECTED', 'DENIED'], 'pageSize' => 50]);

foreach ($page as $claim) { /* items on this page */ }

$page->hasMore();
$next = $page->nextPage();

// Every item across all pages, fetched on demand:
Stedi::listEnrollments()->lazy()->each(fn ($enrollment) => ...);
```

List filters can be PHP arrays and booleans; the package sends them the way
Stedi expects (`status=REJECTED&status=DENIED`, `true`/`false`).

### Multiple API keys

```php
Stedi::withApiKey($tenant->stedi_key)->createEligibilityCheck([...]);
```

`withTimeout()`, `withRetries()`, `withHeaders()` and `withConfig()` return a
copy with one setting changed the same way. Prefer them to changing
`config('stedi.*')` at runtime: the client reads the config once, when a
request or job first uses it, so a later `config([...])` call only reaches the
next request or job.

## Webhooks

Stedi delivers events (enrollment status changes, processed transactions,
...) to an [event destination](https://www.stedi.com/docs/healthcare/event-destinations).
The package registers a route for them:

1. Create a destination pointing at `https://your-app.com/stedi/webhook`, in
   the portal or with `Stedi::createEventDestination([...])`.
2. Add its signing secret to `.env`:

   ```dotenv
   STEDI_WEBHOOK_SECRET=whsec_...
   ```

3. Listen for `Cabuz\Stedi\Events\WebhookReceived`, preferably in a queued
   listener:

   ```php
   use Cabuz\Stedi\Events\WebhookReceived;
   use Illuminate\Contracts\Queue\ShouldQueue;

   class HandleStediEvent implements ShouldQueue
   {
       public function handle(WebhookReceived $event): void
       {
           if ($event->is('enrollment.*')) {
               $enrollment = $event->fetch();  // Stedi::getEnrollment($event->resourceId())
               // ...
           }
       }
   }
   ```

   Or listen for one type only. Every documented Stedi event type also fires
   its own class, which extends `WebhookReceived`:

   | Event type | Class in `Cabuz\Stedi\Events` |
   |---|---|
   | `enrollment.activated`, `.rejected`, `.updated` | `EnrollmentActivated`, `EnrollmentRejected`, `EnrollmentUpdated` |
   | `enrollment.task.assigned`, `.completed`, `.deleted` | `EnrollmentTaskAssigned`, `EnrollmentTaskCompleted`, `EnrollmentTaskDeleted` |
   | `transaction.processed` | `TransactionProcessed` (277CA, 835, ...) |
   | `file.processed`, `file.failed` | `FileProcessed`, `FileFailed` |
   | `event.ping` | `EventPing` |
   | `app.installed`, `app.uninstalled` | `AppInstalled`, `AppUninstalled` |

   ```php
   public function handle(TransactionProcessed $event): void
   {
       $transaction = $event->fetch();
   }
   ```

The route checks the [Standard Webhooks](https://www.standardwebhooks.com)
signature and the timestamp (5 minutes of tolerance), answers `400` to
anything unsigned, and acknowledges redeliveries of an event it already
handled without firing it again. If a listener throws, the delivery fails and
Stedi retries it later. Events are thin (type and resource ID), so fetch the
resource in your listener.

Listeners run before Stedi gets its answer, so a slow synchronous listener can
make the delivery time out and come back. Queued listeners answer at once; the
event only holds the payload array, so it queues as is. When your cache store
supports locks (Redis, database, Memcached, DynamoDB, file, array), a second
delivery of an event that is still being handled gets a `409` and Stedi
retries it later, so listeners never run twice for one event.

| Event method | Returns |
|---|---|
| `type()` | `enrollment.activated`, ... |
| `is('enrollment.*')` | whether the type matches |
| `resourceId()`, `resourceType()` | what the event is about |
| `isProduction()` | `false` for test-mode events |
| `fetch()` | the enrollment, transaction, execution or destination, from the API (`fetch($client)` for another key) |
| `$payload`, `$webhookId` | the full event and the delivery ID |

To rotate the secret without downtime, list both while the old one is still
valid: `STEDI_WEBHOOK_SECRET=whsec_new,whsec_old`. Set `STEDI_WEBHOOK_PATH`
to change the URL, or to an empty value to disable the route. To verify
signatures yourself, use `Cabuz\Stedi\Webhooks\WebhookSignature::verify()`.

## Errors

Every error extends `Cabuz\Stedi\Exceptions\StediException`:

| Exception | Status |
|---|---|
| `InvalidRequestException` | 400, 413, 422 |
| `AuthenticationException` | 401 |
| `PermissionDeniedException` | 403, including features not available in test mode |
| `NotFoundException` | 404 |
| `ConflictException` | 409 |
| `RateLimitException` | 429, after retries (`retryAfter()` gives the seconds) |
| `ServerException` | 5xx |
| `ConnectionException` | Stedi could not be reached |
| `MissingApiKeyException` | No API key configured |

```php
try {
    Stedi::createEligibilityCheck($request);
} catch (InvalidRequestException $e) {
    $e->error();        // Stedi's message
    $e->errorCode();    // e.g. "access_denied", when Stedi sends one
    $e->fieldErrors();  // [['path' => '/subscriber/memberId', 'message' => '...'], ...] (fieldList or errors)
    $e->status();       // 400
    $e->response;       // the Illuminate\Http\Client\Response
}
```

`Stedi::lastResponse()` returns the raw response of the most recent request.
The client is bound as a scoped instance, so under Octane and in queue
workers each request or job starts with a fresh client and never sees another
one's response.

## Retries

A request is retried up to 2 times (`STEDI_RETRIES`), with a delay that
doubles after each attempt and follows `Retry-After` when Stedi sends it. No
wait is longer than 10 seconds (`STEDI_RETRY_MAX_DELAY`, in milliseconds), so
a rate-limited request does not hold a web request for minutes:

- **429 Too Many Requests**: retried for every request.
- **5xx and connection errors**: retried for reads, and for writes sent with
  an idempotency key (every claim submission). Other writes, such as
  eligibility checks, are not repeated automatically.

## Logging

```dotenv
STEDI_LOG=debug            # debug, info, warning or error
STEDI_LOG_CHANNEL=stack    # optional
```

`debug` logs every request (method, API, path, status, duration), `info`
retries, `warning` failed requests and `error` server and connection errors.
Request and response bodies, query strings and the API key are never logged,
because they can contain protected health information. Path parameters (IDs)
are logged.

## Artisan commands

```bash
php artisan stedi:check                 # check the API key
php artisan stedi:payers "aetna"        # look up payer IDs
```

## Testing your app

`Stedi::fake()` keeps every request away from Stedi. Without stubs, each
operation answers with Stedi's own example response from its API reference:
an eligibility check comes back with active coverage, a payer search with a
page of payers, a claim submission accepted, and so on. Your code runs
against realistic data with one line:

```php
use Cabuz\Stedi\Facades\Stedi;

Stedi::fake();

$eligibility = Stedi::checkEligibility([...]);
$eligibility->isActive();                       // true
$eligibility->outOfPocket('30', 'IN_NETWORK');  // "8000"
```

Stub the requests your test cares about with `{api}/{path}` keys:

```php
Stedi::fake([
    'healthcare/eligibility-check' => ['errors' => [['code' => '75', 'description' => 'Subscriber/Insured Not Found']]],
    'payers/payers/search*' => ['items' => []],
]);

// ... exercise your code ...

Stedi::assertSent('healthcare/eligibility-check', fn (array $body) => $body['subscriber']['memberId'] === 'AETNA12345');
Stedi::assertNotSent('claims/*');
Stedi::assertSentCount(1);
Stedi::assertNothingSent();
```

The callback gets the JSON body and the `Illuminate\Http\Client\Request`.
`Stedi::sent('payers/*')` returns the matching requests.

The API names are `healthcare`, `claims`, `manager`, `payers`, `enrollment`,
`events` and `core`. Patterns match the full URL, so use a trailing `*` for
requests with a query string. Example responses are a single page (no
`nextPageToken`), and operations without one, such as PDF downloads, return an
empty body. `Stedi::fake(examples: false)` answers every unstubbed request
with an empty `200` instead, as before 1.1.2.

To test your webhook listeners end to end, sign a payload with
`WebhookSignature::sign()`:

```php
use Cabuz\Stedi\Webhooks\WebhookSignature;

$body = json_encode(['id' => 'evt_1', 'type' => 'enrollment.activated', 'resource' => ['id' => 'enr_1', 'type' => 'enrollment']]);
$timestamp = time();

$this->call('POST', '/stedi/webhook', server: [
    'CONTENT_TYPE' => 'application/json',
    'HTTP_WEBHOOK_ID' => 'msg_1',
    'HTTP_WEBHOOK_TIMESTAMP' => (string) $timestamp,
    'HTTP_WEBHOOK_SIGNATURE' => WebhookSignature::sign($body, 'msg_1', $timestamp, config('stedi.webhook.secret')),
], content: $body)->assertOk();
```

## Configuration

| Key | `.env` | Default |
|---|---|---|
| `api_key` | `STEDI_API_KEY` | none |
| `timeout` | `STEDI_TIMEOUT` | `60` seconds |
| `retries` | `STEDI_RETRIES` | `2` |
| `retry_delay` | `STEDI_RETRY_DELAY` | `500` ms |
| `retry_max_delay` | `STEDI_RETRY_MAX_DELAY` | `10000` ms |
| `headers` | none | `[]` |
| `endpoints` | none | Stedi's hosts; override one, e.g. for a proxy |
| `log` | `STEDI_LOG` | none (off) |
| `log_channel` | `STEDI_LOG_CHANNEL` | default channel |
| `webhook.path` | `STEDI_WEBHOOK_PATH` | `stedi/webhook` |
| `webhook.secret` | `STEDI_WEBHOOK_SECRET` | none |
| `webhook.tolerance` | `STEDI_WEBHOOK_TOLERANCE` | `300` seconds |
| `webhook.deduplicate` | none | `true` (uses your default cache store and its locks) |
| `webhook.middleware` | none | `[]` |

## AI assistants

The package ships a [Laravel Boost](https://github.com/laravel/boost)
guideline and skill. Run `php artisan boost:install` (or `boost:update`) and
select `cabuz/laravel-stedi`, and your AI assistant learns how to call Stedi
with this package.

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). The methods are generated from
Stedi's OpenAPI specs: `bin/update-specs` refreshes them and `bin/generate`
rebuilds the methods, the facade docblock, `api.md` and the example
responses `Stedi::fake()` serves (`resources/fakes.json`).

## Security

Please report security issues privately, as described in
[SECURITY.md](SECURITY.md).

## License

The MIT License (MIT). See [LICENSE](LICENSE).
