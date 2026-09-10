---
paths:
  - 'app/**,resources/js/**,routes/**'
---

# Js

## Gate customer reviews with runtime flags
Customer product review UI is controlled by `reviews.product.enabled`; future seller feedback must use `reviews.seller.enabled`. These flags do not control seller/listing moderation or checkout review, and the direct review submission endpoint remains active while product review UI is disabled.
