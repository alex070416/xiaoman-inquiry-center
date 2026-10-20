# beta.12 validation report

Date: 2026-10-20. Scope: legacy request compatibility for server-side message validation in the shared package.

The required-field module accepts the confirmed legacy `nifr_context/nifr_campaign` fields and `nixm_context/fwxm_context` packets from the same submission. Existing signature, host, rules-hash, page and campaign-required flag verification is preserved. The patch does not read saved inquiries, native in-memory attribution records, `_source` history, credentials or customer fields.

## Local checks

- PHP 8.2.34 and 8.4.26: each passed 34 compatibility scenarios / 197 assertions.
- Each PHP version also passed saved-form response (8), asset metadata (9) and privacy-routing (13) cases.
- Node 24: 124 frontend tests and 30 consent-helper checks passed.
- Independent review covered invalid and oversized packets, submitted/POST fields, signature failures, conflicting evidence, non-PM requests and historical-attribution isolation.

The remote PHP 8.2 / 8.4 / 8.5 and frontend matrix remains a release gate. beta.11 checks remain historical evidence for that version, not a substitute for this gate.

## Limits

Legacy signed tokens have no newly introduced expiry or visitor binding. Their valid required flags retain the original meaning and cannot be cancelled by an ordinary campaign field. This compatibility patch does not establish that previously cached HTML represents the current visitor's campaign, and does not claim to repair existing cache contamination.

Deployment and external-service acceptance require separate site evidence. No live inquiry submission or historical conversion replay is part of these local checks. beta.11 artifacts remain immutable; beta.12 uses a new version and signed package.
