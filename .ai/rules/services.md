---
paths:
  - 'app/Services/**'
  - app/Services/ListingService.php
---

# Services

## Approve typed listing brands with listing moderation
Seller-entered brand text stays on the private listing as brand_name. Only approved listings resolve it to an existing or newly created catalog Brand inside the moderation transaction, with an audit entry.

## Synchronize permitted Google taxonomy categories
Activating a Google taxonomy version must synchronize permitted nodes into marketplace categories in one transaction. Excluded IDs and descendants stay reference-only; preserve mapped category slugs, commercial settings, and listing links; deactivate removed mappings without deleting them; leave categories without Google IDs untouched.

## Preserve ordered listings through archival
Seller removal is lifecycle-sensitive: soft-delete a listing only when it has no historical order items. If any order item exists, set the listing status to archived instead so order history remains intact. Both outcomes must remain excluded from public storefront queries.

## Keep cart and payment operations replay safe
Guest carts live in the session and merge through durable cart_merges tokens; preserve these tokens when changing authentication handoff. Cart, drawer, and checkout use CartService summaries, with checkout.shipping_fee charged once per customer order. Stripe payment expires_at is the local 30-minute reservation deadline: reconcile and expire the provider session before releasing inventory, and keep provider_reference as the PaymentIntent ID for refunds.
