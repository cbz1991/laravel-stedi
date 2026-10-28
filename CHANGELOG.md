# Changelog

All notable changes to `laravel-stedi` will be documented in this file.

## Unreleased

## 1.1.1 - 2026-10-28

- `checkEligibility()` returns the payer's AAA rejections (which Stedi sends
  as a `400` with the usual response body) instead of throwing, so
  `hasErrors()` and `errors()` see them. Requests Stedi rejects before they
  reach the payer still throw `InvalidRequestException`.
- `fieldErrors()` also reads field errors from the `errors` list that newer
  Stedi endpoints send, skipping AAA rejections.
- While rotating webhook secrets, a secret that is not valid base64 no longer
  makes every delivery fail when it is listed before the valid one.
- More tests for error mapping (404, 409, 429 with `Retry-After`, non-JSON
  5xx bodies), and the README explains when config changes made at runtime
  take effect.

## 1.1.0 - 2026-10-28

- `Stedi::checkEligibility()` returns an `EligibilityResponse` with
  `isActive()`, `copay()`, `coinsurance()`, `deductible()`,
  `deductibleRemaining()`, `outOfPocket()`, `outOfPocketRemaining()`,
  `benefits()` and the payer's AAA `errors()`, filtered by service type,
  network and coverage level. Array access still reads the raw response.
- Testing: `Stedi::assertSent()`, `assertNotSent()`, `assertSentCount()`,
  `assertNothingSent()` and `sent()`, matched with the same `{api}/{path}`
  keys as `Stedi::fake()`.
- Every documented webhook event type also fires its own event class
  (`EnrollmentActivated`, `TransactionProcessed`, `FileFailed`, ...), which
  extends `WebhookReceived`. `WebhookReceived::fetch()` loads the resource the
  event is about.
- `PollEligibilityBatch` job: checks a batch eligibility check until it
  finishes, without blocking a worker, and fires `EligibilityBatchFinished`.
- Requires `illuminate/bus` and `illuminate/queue` (part of every Laravel app).
- Fixed the indentation of the webhook listener example in the README.

## 1.0.1 - 2026-10-28

- The client is bound as a scoped instance instead of a singleton, so under
  Octane and in queue workers `Stedi::lastResponse()` (which can hold patient
  data) never carries over to another request or job.
- Concurrent deliveries of the same webhook event no longer fire
  `WebhookReceived` twice: the route takes a cache lock and answers `409` to
  the second delivery, which Stedi retries later.
- Waits between retries are capped by the new `retry_max_delay` option
  (`STEDI_RETRY_MAX_DELAY`, 10 seconds by default), also when `Retry-After`
  asks for longer. It was 60 seconds per attempt before.
- Added the Packagist keywords `medical-billing`, `remittance` and
  `insurance`; the README recommends queued webhook listeners.

## 1.0.0 - 2026-10-28

- First release.
- One method per Stedi operation (90 in all), named like the official SDK and
  routed to the right API: eligibility (real-time and batch), claims,
  claim status, remittances, coordination of benefits, insurance discovery,
  payers, enrollment, event destinations and Stedi Core.
- Webhook route with Standard Webhooks signature verification, secret
  rotation and redelivery deduplication, firing `WebhookReceived`.
- Automatic idempotency keys for claim submissions, so retries never submit
  twice; reads and keyed writes are retried after server errors.
- Cursor pagination with `CursorPage::lazy()`, typed exceptions with Stedi's
  field errors, PHI-safe logging, `Stedi::fake()` and per-client options.
- `stedi:check` and `stedi:payers` Artisan commands.
- Laravel Boost guideline and skill.
