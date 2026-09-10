---
paths:
  - 'app/**,resources/js/**,routes/**'
---

# Js

## Gate customer reviews with runtime flags
Customer product review UI is controlled by `reviews.product.enabled`; future seller feedback must use `reviews.seller.enabled`. These flags do not control seller/listing moderation or checkout review, and the direct review submission endpoint remains active while product review UI is disabled.

## Share Meta event IDs across browser and server
For ViewContent, AddToCart, InitiateCheckout, and Purchase, send the same event ID through the browser dataLayer and Conversions API so Meta deduplicates the pair. Keep product content IDs as strings: use the variant ID when selected, otherwise the listing ID; keep the listing ID only as item_group_id.
