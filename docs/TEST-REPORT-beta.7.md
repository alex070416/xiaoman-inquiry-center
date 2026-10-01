# beta.7 corrective validation

This prerelease is restricted to the Fangwei pilot. It is not a rollout approval.

## Corrections

- Build explicitly configured thank-you URLs from the unfiltered WordPress home option. TranslatePress filters `home_url()` during language requests; applying an already prefixed configured path could add the language twice.
- Register LiteSpeed optimization, deferred JavaScript and guest-mode exclusions while the conversion module is active. Preserve all existing exclusion entries.
- Remove the temporary receipt token from rendered language links and from links created later when clicked, as well as from the browser address.

## Local evidence

- Inspected Bricks native save integration: 33 checks passed.
- Saved receipt, consent, translated routing, optimizer exclusions, CRM retries and separate funnel stages: 64 checks passed in isolated WordPress/SQLite. Real MariaDB checks remain part of live acceptance.
- JavaScript suites: 26 checks passed, including duplicate receipt claims, cleaned-URL refreshes, language-link token cleanup and conditional message requirements.
- Signed updater/package checks: 11 passed.
- Package contains 130 files under one `xiaoman-inquiry-center/` folder; signing material stays outside the repository.

## Acceptance status

Production test inquiries and Google report reconciliation are tracked privately. A browser callback proves dispatch execution, not Google ingestion. Real Google Ads attribution and processing time must be accounted for before accepting the pilot or expanding to other sites. No historical browser conversions or offline customer uploads are performed.
