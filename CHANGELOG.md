# Changelog

All notable changes to `laravel-stedi` will be documented in this file.

## Unreleased

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
