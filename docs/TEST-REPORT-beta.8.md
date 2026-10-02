# beta.8 conversion dispatch validation

This prerelease is limited to one pilot site. Local validation does not authorize rollout to other sites or establish Google ingestion or attribution.

## Changes

- Require a responsive configured Google destination before claiming a saved conversion. A queued `gtag` function alone does not prove destination readiness. An Ads destination can respond without a click identifier.
- Assign an owner to each new channel claim. If the current page can establish that it never invoked the Google event, it may release only its own unused claim and retry after consent or page visibility returns.
- Keep uncertain outcomes claimed. Lost claim responses, thrown event calls, historical claims and another page's claims are not automatically replayed.
- Match callbacks to the current claim owner. A callback remains a browser tag callback, not a Google delivery receipt.
- Migrate enabled sites during WordPress initialization because plugin updates do not rerun activation hooks. Verify the added columns, use a nonblocking migration lock and throttle failed attempts. Migration failure retains the legacy script and existing state.
- Support old cached clients during rollout. Register the owned endpoint separately so new clients cannot consume an old claim through a rolled-back server.
- Bind contact listeners once when initialized repeatedly and preserve the public consent API. Protect both current and fallback scripts from optimization while retaining existing exclusions.

## Local checks

| Gate | Result |
|---|---:|
| Complete JavaScript DOM suites | 43 passed |
| Owner claim/release SQLite protocol | 16 passed |
| WordPress migration and rolling interface fixtures | 22 passed |
| Actual WordPress/MySQL migration in isolated temporary tables | 12 passed |
| Inspected Bricks native save and CRM queue integration | 33 passed |
| Saved conversion, consent, language paths and separate stages in isolated WordPress/SQLite | 64 passed |
| Five sanitized legacy migration profiles | 23 passed per profile |
| Existing signed updater and archive-layout fixture | 11 passed |

The integration uses WordPress 7.1.2 and inspected Bricks Form, Save_Submission and Submission_Database source. Only environment helpers and remote CRM responses are substituted. Commercial Bricks source and private site data are not included in this repository. PHP 8.4 syntax checks passed for the changed PHP files; the PHP integration suites ran on PHP 8.5.

The actual WordPress/MySQL gate used isolated temporary tables with the unchanged candidate PHP. It added only the two owner columns, preserved existing states, verified repeated migration, limited SQL to the test tables and completed cleanup. It did not migrate the production conversion table.

The previous integration expected only the current conversion script in optimizer exclusions. Its expectation now includes the legacy fallback script as well; the original requirement to preserve unrelated exclusions remains checked. No original semantic test was removed or skipped.

The updater gate uses an existing public signed fixture. No beta.8 release package was signed or built as part of these checks.

## Signed release package

The complete beta.8 package was subsequently built with the existing signing key and independently checked against the public key, signed manifest and frozen full file set. It contains 131 files; only the entry version, conversion PHP, conversion JavaScript and exact legacy JavaScript copy differ from beta.7.

Package SHA256: `0c77f7aa9f727ade877976630b46138317be6fe86949d5d1c2bf64b5c6b395c6`.

## Pending acceptance and limits

- Production migration and post-installation checks remain part of pilot deployment acceptance; the isolated MySQL gate is not a production-migration result.
- Real browser Google requests, user consent, Google processing and date-aligned report reconciliation still require pilot acceptance. A tag callback cannot prove receipt or deduplication in the processed report.
- Reloading the cleaned thank-you URL does not restore the removed receipt in this version. Full reload recovery is not implemented.
- Migration failure falls back to the earlier dispatch behavior and retains its known missing-report risk. Historical claims are preserved and never reset to make the reports match.
- Claim release relies on the honest client establishing that no event invocation occurred; it does not prevent a dishonest client from making a false release claim.
- No historical browser conversions or offline customer-data uploads are performed.

## Portable repository gates

```sh
npm test
php -d extension=pdo_sqlite tests/owner-protocol.test.php
php -d extension=pdo_sqlite tests/schema-rolling.test.php
php -d extension=sodium -d extension=zip tests/updater.test.php
php -d extension=sodium tests/compatibility.test.php fangwei
```

Run the compatibility fixture separately for the other four supported profiles. SQLite and the interface fixtures do not replace the real MySQL gate.
