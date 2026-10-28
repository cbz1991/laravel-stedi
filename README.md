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

```php
$response = Stedi::createEligibilityCheck([...]);  // JSON eligibility check
Stedi::eligibilityCheck([...]);                    // the v3 (Change Healthcare-compatible) endpoint
Stedi::eligibilityRawX12Check(['x12' => '...']);   // raw X12 270
Stedi::coordinationOfBenefits([...]);
Stedi::insuranceDiscoveryCheck([...]);
```

Batch checks run in the background:

```php
$batch = Stedi::batchEligibilityChecks(['items' => [...]]);
Stedi::getBatchItems($batch['batchId'])->lazy()->each(fn ($item) => ...);
```

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
copy with one setting changed the same way.

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

3. Listen for `Cabuz\Stedi\Events\WebhookReceived`:

   ```php
   use Cabuz\Stedi\Events\WebhookReceived;

   public function handle(WebhookReceived $event): void
   {
       if ($event->is('enrollment.*')) {
           $enrollment = Stedi::getEnrollment($event->resourceId());
           // ...
       }
   }
   ```

The route checks the [Standard Webhooks](https://www.standardwebhooks.com)
signature and the timestamp (5 minutes of tolerance), answers `400` to
anything unsigned, and acknowledges redeliveries of an event it already
handled without firing it again. If a listener throws, the delivery fails and
Stedi retries it later. Events are thin (type and resource ID), so fetch the
resource in your listener, ideally in a queued one.

| Event method | Returns |
|---|---|
| `type()` | `enrollment.activated`, ... |
| `is('enrollment.*')` | whether the type matches |
| `resourceId()`, `resourceType()` | what the event is about |
| `isProduction()` | `false` for test-mode events |
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
    $e->fieldErrors();  // [['path' => '/subscriber/memberId', 'message' => '...'], ...]
    $e->status();       // 400
    $e->response;       // the Illuminate\Http\Client\Response
}
```

`Stedi::lastResponse()` returns the raw response of the most recent request.

## Retries

A request is retried up to 2 times (`STEDI_RETRIES`), with a delay that
doubles after each attempt and follows `Retry-After` when Stedi sends it:

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

`Stedi::fake()` stubs the APIs with `{api}/{path}` keys. Requests that don't
match get an empty `200`, so tests never reach Stedi.

```php
use Cabuz\Stedi\Facades\Stedi;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

Stedi::fake([
    'healthcare/eligibility-check' => ['planStatus' => [['status' => 'Active Coverage']]],
    'payers/payers/search*' => ['items' => [], 'nextPageToken' => null],
]);

// ... exercise your code ...

Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/eligibility-check')
    && $request['subscriber']['memberId'] === 'AETNA12345');
```

The API names are `healthcare`, `claims`, `manager`, `payers`, `enrollment`,
`events` and `core`. Patterns match the full URL, so use a trailing `*` for
requests with a query string.

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
| `headers` | none | `[]` |
| `endpoints` | none | Stedi's hosts; override one, e.g. for a proxy |
| `log` | `STEDI_LOG` | none (off) |
| `log_channel` | `STEDI_LOG_CHANNEL` | default channel |
| `webhook.path` | `STEDI_WEBHOOK_PATH` | `stedi/webhook` |
| `webhook.secret` | `STEDI_WEBHOOK_SECRET` | none |
| `webhook.tolerance` | `STEDI_WEBHOOK_TOLERANCE` | `300` seconds |
| `webhook.deduplicate` | none | `true` (uses your default cache store) |
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
rebuilds the methods, the facade docblock and `api.md`.

## Security

Please report security issues privately, as described in
[SECURITY.md](SECURITY.md).

## License

The MIT License (MIT). See [LICENSE](LICENSE).
