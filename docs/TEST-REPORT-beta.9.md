# beta.9 pending-receipt and lead-date verification report

Status: 2026-10-06. The integration target is `2.0.0-beta.9`, a prerelease, not stable `2.0.0`. The results below distinguish reviewed patch evidence from checks on the final integrated source and signed release artifact. A successful pilot test does not establish complete advertising reconciliation or authorize rollout to other sites.

## Intended package contents

- Include the 2026-10-06 owned-protocol JavaScript and PHP patch together. PHP publishes the original valid receipt expiry and definitely-pending channel mask; JavaScript can resume only those unclaimed channels in the same tab within that original deadline.
- Declare `xi_conversion_pending_v1` as sessionStorage in the site's actual published analytics-purpose service. The public `pending_storage_service` maps to the validated `analytics_service`; it is not an independent grant of consent.
- Persist only host, inquiry identifier, original receipt, expiry and eligible channel mask. Do not put owner tokens, email, phone or enhanced-conversion hashes in that pointer. Scrub the receipt from the URL before Google code can read it.
- Remove a channel's recovery eligibility before issuing its claim. Restore it only after a strictly typed `success === false` response whose data contains exactly one `code`, equal to `consent`, `config` or `protocol`; these explicit checks precede any receipt update. Unknown refusals, extra fields, lost/parsing-failed claim responses, already-claimed/callback outcomes and expired receipts cannot be replayed. Existing server owner and atomic-claim rules remain in force; no new receipt columns or extended TTL are part of this patch.
- Restore the legacy CRM name-date rule: original saved timestamp plus 12 hours, then format `YYMMDD` in the WordPress site's timezone. Remarks, saved UTC and actual conversion times remain original. Retrying derives the same name date once from the original timestamp; existing successful CRM records are not renamed or recreated.
- Retain the exact legacy fallback behavior and keep the signed updater's existing trust identity. A new version must contain both date and conversion changes rather than overwrite one hotfix with an older package.

## Evidence already observed before final integration

| Scope | Observed result | Limit |
| --- | --- | --- |
| Reviewed conversion-patch JavaScript fixtures | 52 passed, no external requests | Isolated DOM/CMP/ledger substitutes; includes baseline reproductions and candidate behavior |
| Reviewed conversion-patch PHP metadata fixtures | 9 passed, no unexpected writes | Isolated valid-receipt metadata and owned/legacy asset checks |
| Actual CRM provider date payload regression | 44 passed, zero HTTP attempts | Payload construction only; no queue/CRM delivery |
| Canonical-source date regression and CI entry | Local 44 passed, zero HTTP; regression added to PHP CI | Remote CI has not been evidenced here |
| Single-site two-file conversion hotfix | PHP syntax, file readback, resource revision and anonymous no-store thank-you checks passed | Installed main version remained beta.8; this is not a beta.9 package check |
| Single-site provider hotfix | Atomic file replacement and synthetic date-boundary/repeated-payload checks passed | No website or CRM records created by the synthetic check |
| One separately authorized marked direct-access test | Native save and CRM success; GA4 realtime observed one matching lead; successful thank-you refresh did not change claim/callback times | No real ad click was used; does not prove Ads attribution or all refresh/consent cases |

Private deployment logs, site/account identifiers, customer data, receipt values and backups are intentionally absent from this report.

## Final beta.9 gates

The following integrated-source and artifact results were observed on 2026-10-06. They do not replace browser, database or processed-advertising acceptance.

| Gate | Current status |
| --- | --- |
| Integrated JavaScript suite | 76/76 passed: 64 conversion cases and 12 existing form/frontend cases |
| PHP syntax | All 121 PHP files passed on PHP 8.2.34 and 8.4.26 |
| PHP metadata, owner and schema substitutes | On each PHP version: 9 metadata, 16 owner and 22 schema checks passed; schema fixtures do not prove every production MySQL behavior |
| Canonical provider date, updater and hardened builder | On each PHP version: 44 date, 11 updater and 29 synthetic builder checks passed; builder fixtures do not read real signing material or sign the actual plugin |
| Sanitized legacy interface profiles | Five profiles, 23 checks each, passed on both PHP versions; not five-site online deployment or a fresh native Bricks save/CRM delivery test |
| Native Bricks save and actual CRM queue regression | Not rerun in this integration because the relevant implementation was unchanged; retain the earlier evidence and its scope |
| Exact plugin tree and beta.9 entry-version freeze | 131 plugin files; packaged file bytes match the shared release source |
| Signed beta.9 ZIP/envelope | Built with the existing signing identity; public key unchanged and signature verified |
| ZIP/manifest/version/URL/hash and full plugin-source equality | Passed; SHA-256 `f319dc012f18fba24f71d707895041dda0a5b710ff414a5527877ca06f3a531d` |
| GitHub prerelease publication and remote CI | Not yet evidenced |
| Beta.9 production install and processed advertising reconciliation | Not passed by the evidence in this report |

## Site prerequisites and acceptance limits

Configure the existing analytics service to disclose the sessionStorage key, its recovery-only purpose, original maximum 30-minute expiry, tab lifecycle and explicit-withdrawal cleanup. Validate the actual published Unique Name and purposes; checking `analytics_storage` does not prove that the storage key was declared. Keep GA4, Ads storage and enhanced-data consent separate, and do not force consent to equalize counts.

The pilot policy uses Every counting and a 30-day click window for each form conversion action, with native Ads primary and the GA4 import secondary. These are per-account settings, not package mutations. Count changes affect future reporting, cannot recover unsent historical events, and do not make total website submissions equal attributed Ads conversions. See [Google conversion-counting guidance](https://support.google.com/google-ads/answer/3438531).

Reconcile by actual conversion date, account timezone and unique inquiry ID; separately exclude marked and manually-confirmed tests, duplicate/old-path records, denied measurement and attribution-ineligible visits. Do not add the two Ads routes. Keep pending, claimed, callback, GA4 receipt and processed Ads attribution distinct. The existing historic discrepancy remains unresolved; four additional-site migrations are not accepted. Recheck the reference site's native fields, metadata, queue keys, CRM mapping, tracking ownership and consent declarations before its future migration, preserving its existing online tracking meanwhile.

## Recovery and rollback boundaries

- Restore only reviewed files and necessary scoped configuration. Retain new inquiries, queues, CRM IDs, stages and the existing conversion ledger; do not restore a whole pre-pilot database, clear claims or replay history.
- Pending recovery is limited to the original tab and original deadline. Storage unavailable from the start may retain the earlier in-memory path without promising reload recovery; uncertain persisted state stops claiming.
- A callback, including a timeout callback, is not proof of Google ingestion. A request that never returns can remain busy, and an unsuccessful acknowledgement can leave claimed state. Do not automatically resend these uncertain outcomes.
- No automatic offline customer-data uploads, historical renames or retroactive browser conversions are introduced.

## Release review

The hardened builder requires an existing signing seed and an existing matching release public key. A missing seed, missing key or mismatched key fails before package creation and does not overwrite the key. The self-contained fixture suite verifies these refusals and normal signing with synthetic temporary identities. Preserve the pinned public key, review the complete frozen source tree, then independently verify the envelope signature, manifest version/download URL, ZIP hash and every packaged file. A package-specific older-version allowlist cannot certify this new version unchanged. A manually uploaded prerelease requires its own validation because the normal updater skips prereleases and manual local installation is not the stable signed-download path.

No signing secret values, customer data, site identifiers or private backups are included in this report. GitHub publication/remote CI and a beta.9 production installation remain unverified here; real advertising reconciliation has not passed.
