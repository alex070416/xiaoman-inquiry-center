# beta.11 validation report

Date: 2026-10-20. Scope: shared source and local deterministic fixtures. This is a prerelease candidate, not production acceptance.

## Changes

- Administrator-only local readiness inspection, available before the business core is enabled.
- Pure legacy-profile preparation and preview; disabled, verified, resumable import stages.
- Preserve existing unified configuration and unreadable encrypted data; reject profile or required-rule drift on retries.
- Serialize configuration saves, check connection ownership and exact previous values in write statements, invalidate option caches and verify persistence.
- Match plugin header and runtime versions; build from a clean source tree and independently verify signed packages using the existing public key.

Conversion dispatch, consent bridge, CRM sender and lead-date runtime files remain unchanged from the reviewed base commit `004d1b7e8b39d88000c6462bdf30eb1cdac928c0`.

## Local results

| Gate | Result |
| --- | --- |
| Node 24.19.0 frontend tests | 124 passed, 0 failed |
| Consent helper checks | 30 passed, 0 failed |
| PHP 8.2.34 | 28/28 current CI test invocations, 8/8 additional credential-mode invocations, 118/118 file syntax checks |
| PHP 8.4.26 | Same gates passed |
| Save-lock fixture | 7 groups / 37 assertions per PHP version, including process-exit cleanup |
| Configuration fixture | 32 cases / 225 assertions plus missing-credential case / 2 assertions per PHP version |
| Legacy fixtures | 5 profiles, each 126 base + 23 stored-credential + 8 missing-decrypt assertions per PHP version |
| Readiness fixture | 8 groups / 110 assertions per PHP version |

Each local PHP version skips two source-symlink cases because the Windows account cannot create symlinks. ZIP symlink rejection is tested. Linux CI covers the source-symlink cases. PHP 8.5 is a remote CI gate, not a locally executed runtime.

The final PHP run preserved source hashes and Git status. Test fixtures use synthetic records and credentials. No live customer submission is part of these tests.

## Remaining acceptance

Package verification and remote CI are recorded against the frozen commit and generated artifact. Runtime inspection, real forms and consent choices, cache behavior, Google receipt/attribution, and CRM reconciliation require separate site evidence. Local checks and callbacks do not establish those outcomes. Do not infer that all sites are deployed or accepted from this report.
