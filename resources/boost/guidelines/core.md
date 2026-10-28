# Laravel Stedi

`cabuz/laravel-stedi` calls the Stedi healthcare APIs (eligibility checks, claims, claim status, remittances, payers, enrollment, event destinations). Use it instead of hand-written HTTP calls to Stedi.

- Use the `Cabuz\Stedi\Facades\Stedi` facade or inject `Cabuz\Stedi\Stedi`. The API key comes from `STEDI_API_KEY`; `test_` keys only answer Stedi's mock requests.
- Every operation is one method named after Stedi's operation ID, like the official SDK: `Stedi::createEligibilityCheck([...])`, `Stedi::listClaims([...])`, `Stedi::searchPayers([...])`. Look up exact names and endpoints in the package's `api.md`; don't guess.
- Methods take arrays shaped like the Stedi API reference and return decoded JSON arrays; list methods return a `CursorPage` (`lazy()` streams every page); file downloads return the raw contents.
- Claim submissions get an idempotency key automatically; pass your own (`idempotencyKey: "invoice-{$id}"`) to make your own retries safe.
- For eligibility answers (active coverage, copay, deductible, out-of-pocket), use `Stedi::checkEligibility([...])`, which returns an `EligibilityResponse` with helpers, instead of walking the raw response.
- Handle Stedi webhooks by listening for `Cabuz\Stedi\Events\WebhookReceived` or a typed subclass such as `EnrollmentActivated` or `TransactionProcessed` (the package's route verifies signatures); set `STEDI_WEBHOOK_SECRET`. Events are thin: `$event->fetch()` loads the resource. Use queued listeners.
- Never log or dump request bodies: they contain patient data. In tests use `Stedi::fake(['healthcare/eligibility-check' => [...]])`, `Stedi::assertSent('healthcare/eligibility-check', fn (array $body) => ...)` and Stedi's mock values, never real patient data.
