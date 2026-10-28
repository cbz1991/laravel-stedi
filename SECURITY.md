# Security Policy

`laravel-stedi` handles your Stedi API key and, through it, protected health
information (patient names, member IDs, dates of birth, claims). A bug that
leaks the key or patient data (for example into logs, exception messages or
events), accepts unsigned webhooks, or sends requests somewhere other than
Stedi is treated as a security issue.

## Supported versions

Only the latest release receives security fixes.

## Reporting a vulnerability

Please **do not open a public issue** for security problems, and never include
real patient data in a report.

Report them privately through GitHub instead:

1. Go to the [Security tab](https://github.com/cbz1991/laravel-stedi/security)
   of the repository.
2. Click **Report a vulnerability**.
3. Describe the issue, the affected versions and, if possible, the steps to
   reproduce it.

You'll get a reply within a few days. Once the issue is confirmed, a fix is
released as soon as possible and you'll be credited in the changelog unless
you'd rather stay anonymous.

## Issues in Stedi itself

This policy covers the package only. If the problem is in the Stedi APIs or
service, report it to [Stedi](https://www.stedi.com) directly.
