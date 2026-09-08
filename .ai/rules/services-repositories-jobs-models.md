---
paths:
  - 'app/{Services,Repositories,Jobs,Models}/**'
---

# Services Repositories Jobs Models

## Preserve auction fallback inventory and payment lifecycle
Auction bids are direct per-unit commitments with no payment authorization at bid time. Each distinct fallback bidder gets a fresh fixed 24-hour Stripe-only payment window. Keep the auction lot reserved across fallback attempts; release it only for cancellation, no bids, or bidder exhaustion. Feature shutdown blocks creation and bidding but must not block closure, payment, or fulfillment of existing auctions.
