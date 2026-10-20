# Purpose consent source integration

This is a source candidate for the universal beta.11 package, carrying forward the reviewed beta.10 purpose-consent integration. It is not approved for a stable Release or automatic updates. Owned assets retain their `-purpose-v1` revision because their runtime code is unchanged. The source change does not write site options or migrate consent during an upgrade. See [UNIVERSAL-PACKAGE-SOP.md](UNIVERSAL-PACKAGE-SOP.md) for the shared-package release and per-site acceptance gates.

`purpose_consent_enabled` is a strict JSON boolean in the raw `xi_conversion_config_v1` option. An absent key keeps the shared source's service consent contract. Explicit `false` selects that contract. Explicit `true` requires the reviewed current-page Google-purpose reader as well as the declared service consent. Other types block enqueue and claims. `XI_Conversions::purpose_mode_status()` reports both raw key presence and mode; defaults alone cannot prove upgrade readiness.

## Existing HJ and HT purpose installations

Their reviewed purpose code predates this key. A missing key is a deployment blocker for these sites. While their original purpose code is still running, use an approved compare-and-swap configuration operation to add boolean `true`, preserving all other keys. Read the raw option back and verify both key presence and exact boolean type/value. A string such as `"true"` is invalid. The offline check `tools/check-purpose-mode.php purpose` accepts raw option JSON on stdin and prints only mode status. Do not store or print credentials with this check.

Before replacing files, verify the owned receipt schema, exact configured GTM container, controlled live reader, region-applicable defaults, disabled Always fire, unique Essential container installation, published CMP service purpose declarations, and both policy guards. Pending Ads storage requires its separately declared service, key, expiry and withdrawal cleanup. External GTM templates live in `required-gtm-templates`; source packaging does not install or publish them.

Replace the paired PHP, dispatcher and bridge together through the reviewed deployment plan, refresh page/optimizer caches, and read public assets back to verify `purpose_consent_enabled: true`. The new bridge differs from the reviewed HJ bridge only in its descriptive header. Its live reads, validated own container/protocol/guards and bounded after-DOMReady CMP wake-up are preserved. In purpose mode missing schema/container/reader blocks sending, and server claims require the owned protocol. Existing ledger, original 30 minute expiry, claim owners, claimed/callback rows and unknown outcomes are retained.

For rollback, restore the original paired purpose files first, then restore only the mode key's exact prior image using a checked configuration operation. Preserve receipt and CRM ledgers, queue state, historical cursors and all consent records.

This rule takes precedence over the older generic rollback in `CONVERSION-PILOT-SOP.md` for HJ/HT, which already transferred event ownership. Keep old business sender tags disabled and do not resend historical events. If the HJ CMP GTM change itself needs rollback, use the already transferred version 14 baseline; restoring pre-transfer business tags is a separate migration and is not part of this source rollback.

## Service sites and mode changes

The missing/false mode keeps the shared source's existing owned sender and rolling-schema legacy fallback. Installing this source does not establish the external reader or authorize a site to migrate to purpose mode. A service-to-purpose transition needs separate site acceptance: stop prior business senders, refresh caches, account for already open service-only `owned-v1` pages, and validate pending handling. The shared owned protocol cannot distinguish a previously opened service page from a purpose page. HJ/HT are already purpose installations; their upgrade is not this transition.

New settings forms include a versioned hidden control marker. A settings form opened before the control existed preserves the stored purpose/CRM value when saved. A new form's explicit unchecked control selects service mode or pauses CRM respectively. Existing capability and nonce checks apply.

## Preserved behavior and validation limits

FW same-tab pending storage, original server expiry, atomic claim/owner/release, no replay of uncertain outcomes, MCP default masking/read-only behavior, CRM idempotency, profile mappings and 18:00 lead naming remain in the clean shared base. CRM pause gates retain the existing behavior that requests created while CRM is paused can have `sync_requested=false`; resuming CRM does not automatically send every paused-period inquiry. In-flight work can complete, and uncertain results require human reconciliation.

Local tests cover fixture behavior only. Mobile acceptance, real Ads attribution, EC network payloads and CRM failure/retry behavior on the live site remain pending. Separately, the coordinating task reports that the real MCP connection, all five read-only tools and default masking of the six test records have now passed; that evidence is separate from this local suite. Do not interpret tag callbacks or local mocks as Google delivery acceptance.
