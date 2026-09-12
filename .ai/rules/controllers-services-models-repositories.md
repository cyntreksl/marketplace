---
paths:
  - 'app/{Http/Controllers,Services,Models,Repositories}/**/*Checkout*.php'
---

# Controllers Services Models Repositories

## Guest checkout identity and access
Buy-now checkout supports a nullable authenticated buyer. Persist the normalized contact_email snapshot, bind idempotency to checkout_token plus buyer ID or guest checkout_identity_hash, and store only the SHA-256 guest access token hash. Guest confirmation/payment access must validate the bearer token; auction bidding and buyer-account features remain authenticated.
