---
paths:
  - 'app/{Services,Repositories,Models}/**'
---

# Services Repositories Models

## Keep product costs and sourcing details private
Product supplier_name and internal_notes, simple-product cost_price, and variant cost_price are private. Hide them by default and expose only on authorized seller/admin product reads. Preserve omitted costs during variant synchronization; explicit null clears them. Live private edits must use the restricted internal-details workflow without changing public details or moderation status.

## Preserve customer order numbering and COD limits
Allocate PRO customer order numbers from the locked persistent sequence, shared by regular and auction orders, never from database IDs or by resetting the counter. Existing orders retain their numbers. COD must be at most LKR 5,000 including delivery, checked again against locked final prices; auction offers remain Stripe-only.
