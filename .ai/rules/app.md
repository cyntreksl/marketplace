---
paths:
  - 'app/**'
---

# App

## Separate category administration from taxonomy availability
A category is storefront-available only when it is not archived, `is_active` is true, and `is_taxonomy_available` is not false. Treat `is_active` as the administrator's durable choice and let taxonomy synchronization update only `is_taxonomy_available`; activation changes cascade through the applicable subtree, including escalation to the highest inactive ancestor when reactivating a descendant.

## Preserve the latest Meta click attribution
When Meta Conversions is enabled, persist each new valid case-sensitive `fbclid` as a 90-day `_fbc` cookie and reuse it across ViewContent, AddToCart, InitiateCheckout, and Purchase. Preserve an unchanged `_fbc`, replace it when a different click ID arrives, and never synthesize `_fbp`.
