---
paths:
  - 'app/**'
---

# App

## Separate category administration from taxonomy availability
A category is storefront-available only when it is not archived, `is_active` is true, and `is_taxonomy_available` is not false. Treat `is_active` as the administrator's durable choice and let taxonomy synchronization update only `is_taxonomy_available`; activation changes cascade through the applicable subtree, including escalation to the highest inactive ancestor when reactivating a descendant.

## Preserve the latest Meta click attribution
When Meta Conversions is enabled, capture and send `_fbc` for genuine, case-sensitive Meta clicks independently of marketing consent; never fabricate it. Preserve valid Parameter Builder appendices and reuse `_fbc` across ViewContent, AddToCart, InitiateCheckout, and Purchase, replacing it only for a genuinely different click. Generate, preserve, set, and send `_fbp` only when the versioned consent cookie grants marketing consent, and discard the Parameter Builder's automatic `_fbp` otherwise.

## Preserve the complete Meta click identifier
Treat genuine fbclid values as case-sensitive opaque tokens that may contain dots, underscores, and hyphens. Pass them unchanged to Meta's parameter builder, and retain a valid existing _fbc cookie when the builder cannot re-parse a dotted payload.
