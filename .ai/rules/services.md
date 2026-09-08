---
paths:
  - 'app/Services/**'
  - app/Services/ListingService.php
  - 'app/Services/Seo*CheckService.php'
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

## Keep the homepage hero as the selected single artwork
The homepage hero is the selected home-deals-banner.png artwork, displayed without added text or gradients; legacy hero promotions must not override it. Secondary and flash-sale promotions remain scheduled. Version this static banner URL from its contents because the R2 custom domain can cache a missing object before upload.

## Seller storefront privacy and branding publication
Public stores and seller summaries must use SellerSummaryService's explicit allowlist and approved, active seller eligibility. Scope catalog filters and aggregate counts in repositories with Listing::publiclyVisible. Branding saves publish immediately; upload replacements to generated seller-specific R2 paths, persist successfully before deleting previous objects, and keep existing branding on failures.

## Reconcile Merchant imports only against a stable catalog
Discovery XML is generated live; keep the scheduled Merchant URL source as the only catalog upload path. Merchant offers include active variants, so reconcile offer IDs/counts separately from canonical sitemap URLs. Compare Google's processed count only when a successful pre-import baseline precedes the upload and its fingerprint still matches; pending reconciliation must not produce a false recovery notice. Never expose credentials or raw provider errors in monitoring logs or alerts.
