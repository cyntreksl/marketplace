# Marketplace MVP Implementation Backlog

## Summary

This backlog delivers a trusted Sri Lankan multi-seller electronics marketplace using Laravel 13, Inertia React, PostgreSQL in production, Stripe, bank transfer, policy-controlled COD, and a manual courier adapter. Every epic, story, and task below is implemented through small, independently testable increments.

## Shared Foundation

### Epic 1 - Platform, UX, and domain foundation

**Description:** Build the marketplace data model, authorization model, operating rules, reusable user interface, and transaction boundaries.

**Scope:** Marketplace schema, roles, audit trail, settings, queues, object storage, Laravel services/repositories, and responsive accessible UI primitives.

**Acceptance Criteria:** All portal pages support responsive layouts, keyboard navigation, clear loading/empty/error states, and light/dark appearance.

#### Story 1.1 - Marketplace data architecture

- **Task: Define marketplace schema.** **Description:** Model sellers, catalogue, listings, auctions, orders, payments, delivery, financial ledger, returns, reviews, notifications, and audit logs. **Scope:** Migrations, Eloquent models, factories, and indexes. **Acceptance Criteria:** Foreign keys, status history, immutable financial/bid entries, and commission snapshots are tested.
- **Task: Establish services and repositories.** **Description:** Put transactions and workflows in focused services that depend on repository contracts. **Scope:** Application services and container bindings. **Acceptance Criteria:** Controllers only validate, authorize, delegate, and respond.

#### Story 1.2 - Roles, audit, and policy

- **Task: Implement role-based access.** **Description:** Support Buyer, Individual Seller, Business Seller, Admin, Finance Admin, and Super Admin permissions. **Scope:** Roles, policies, middleware, and portal routing. **Acceptance Criteria:** Every protected action denies access to unauthorized actors.
- **Task: Implement audit and configuration.** **Description:** Record critical actions and keep operating rules versioned and editable. **Scope:** Audit logs and marketplace settings. **Acceptance Criteria:** Orders preserve historic financial settings while active workflows read current policy.

### Epic 2 - Identity and integrations

**Description:** Extend existing Fortify accounts for seller onboarding and integrate payments/courier boundaries safely.

**Scope:** Verified accounts, KYC, private documents, Stripe, bank transfer, COD, manual courier workflow, webhooks, and queues.

**Acceptance Criteria:** Sensitive operations are authorized, idempotent, retryable, and auditable.

#### Story 2.1 - Seller onboarding

- **Task: Extend account intent.** **Description:** Preserve buyer registration and support individual/business seller onboarding. **Scope:** Fortify account setup and seller profile. **Acceptance Criteria:** Seller accounts start pending review and must verify email before protected actions.
- **Task: Secure seller evidence.** **Description:** Collect addresses, bank details, terms acceptance, and verification documents. **Scope:** Private storage and Admin review. **Acceptance Criteria:** Only authorized staff access documents and all decisions include a reason.

#### Story 2.2 - Provider boundaries

- **Task: Implement Stripe payment boundary.** **Description:** Provide payment creation, verified callback, idempotency, and refund interfaces. **Scope:** Payment service and webhooks. **Acceptance Criteria:** Replayed callbacks cannot duplicate payment, order, or refund records.
- **Task: Implement manual courier boundary.** **Description:** Record courier assignment, waybill, tracking, exceptions, and delivery status. **Scope:** Shipment service and Admin controls. **Acceptance Criteria:** Paid orders can move through delivery with complete status history.

## Front Store

### Epic 3 - Public discovery

**Description:** Give guests a trustworthy, quick, responsive marketplace browsing experience.

**Scope:** Homepage, catalog, filters, listing details, seller profiles, featured content, and sign-in prompts.

**Acceptance Criteria:** Guests can browse only approved public listings and share filtered catalog URLs.

#### Story 3.1 - Homepage and catalogue

- **Task: Build marketplace home.** **Description:** Show featured, ending-soon, new, and Buy Now listings. **Scope:** Public home page and promotional content. **Acceptance Criteria:** Admin-selected content is displayed in polished mobile and desktop sections.
- **Task: Build catalog search.** **Description:** Add keyword, category, brand, price, condition, type, and location filters. **Scope:** Public listings index. **Acceptance Criteria:** Filter state survives navigation and excludes unpublished stock.

#### Story 3.2 - Listing confidence

- **Task: Build Buy Now details.** **Description:** Show media, condition, warranty, stock, price, seller, and delivery information. **Scope:** Fixed-price product page. **Acceptance Criteria:** Buyers see clear availability and purchase actions.
- **Task: Build auction details.** **Description:** Show price, bid count, next bid, countdown, reserve state, and seller. **Scope:** Auction page. **Acceptance Criteria:** Bid validation is always server-authoritative.

## Buyer Portal

### Epic 4 - Buyer auctions and engagement

**Description:** Let buyers watch items, bid safely, and manage auction outcomes.

**Scope:** Watchlists, activity, proxy bids, anti-sniping, closing jobs, and transactional notifications.

**Acceptance Criteria:** Simultaneous valid bids produce a deterministic result and immutable history.

#### Story 4.1 - Buyer activity

- **Task: Add watchlists and active bids.** **Description:** Let buyers save listings and view their bidding activity. **Scope:** Buyer dashboard. **Acceptance Criteria:** A buyer only sees their own activity.
- **Task: Add auction notifications.** **Description:** Queue bid, outbid, ending-soon, won/lost, and reserve notifications. **Scope:** Notification service. **Acceptance Criteria:** Notifications are deduplicated and tested with Laravel fakes.

#### Story 4.2 - Auction engine

- **Task: Place bids atomically.** **Description:** Enforce eligibility, increments, auction state, and proxy maximums using database locks. **Scope:** Bid service and ledger. **Acceptance Criteria:** Parallel bid tests cannot produce an invalid winner or price.
- **Task: Automate auction lifecycle.** **Description:** Start/close auctions, extend near-ending bids, and create winner payment flow. **Scope:** Scheduler and queues. **Acceptance Criteria:** Reserve-not-met, no-bid, cancellation, payment-pending, and failed-payment outcomes are deterministic.

### Epic 5 - Checkout and buyer protection

**Description:** Let buyers buy, pay, track, review, and seek support after purchase.

**Scope:** Cart, order splitting, payments, tracking, reviews, returns, and refunds.

**Acceptance Criteria:** A checkout creates one buyer order and independently fulfilable seller orders.

#### Story 5.1 - Checkout

- **Task: Implement cart and order split.** **Description:** Reserve stock and create customer orders, seller orders, items, and commission snapshots. **Scope:** Cart/checkout services. **Acceptance Criteria:** Inventory cannot oversell and seller order states remain independent.
- **Task: Support payment methods.** **Description:** Offer Stripe, bank-proof review, and eligible COD. **Scope:** Buyer checkout. **Acceptance Criteria:** Auctions enforce the winner deadline and cannot use COD.

#### Story 5.2 - Post-purchase experience

- **Task: Build tracking and reviews.** **Description:** Present order timeline, shipment tracking, and post-completion ratings. **Scope:** Buyer portal. **Acceptance Criteria:** Only eligible buyers can leave one review per transaction.
- **Task: Build return requests.** **Description:** Capture return reason and evidence. **Scope:** Buyer protection. **Acceptance Criteria:** Return windows are enforced and evidence is preserved.

## Seller Portal

### Epic 6 - Seller store and listings

**Description:** Enable verified sellers to manage public stores and moderated listings.

**Scope:** Seller profile, draft listings, media, catalog data, inventory, auctions, commissions, and moderation.

**Acceptance Criteria:** Only approved sellers may submit listings; only approved listings are public.

#### Story 6.1 - Store management

- **Task: Build guided onboarding.** **Description:** Create a mobile-friendly seller setup flow. **Scope:** Seller portal. **Acceptance Criteria:** Progress is safe and status/reasons are always visible.
- **Task: Manage public store profile.** **Description:** Maintain public identity and private business/financial data separately. **Scope:** Seller store profile. **Acceptance Criteria:** Private KYC and bank data never appears on public pages.

#### Story 6.2 - Listing lifecycle

- **Task: Create draft/submit workflow.** **Description:** Support product, media, condition, warranty, location, and resubmission data. **Scope:** Seller listing forms. **Acceptance Criteria:** Sellers can draft, submit, revise, and see moderation reasons.
- **Task: Price inventory and commissions.** **Description:** Support fixed-price stock/discounts and auction pricing/timing. **Scope:** Seller pricing forms. **Acceptance Criteria:** Sellers see an estimate; final order commission remains immutable.

### Epic 7 - Fulfilment and finance

**Description:** Give sellers operational and financial control after a sale.

**Scope:** Seller orders, Ready to Ship, shipment status, ledger, available balance, and payouts.

**Acceptance Criteria:** Balances derive from immutable ledger entries rather than current order totals.

#### Story 7.1 - Fulfilment

- **Task: Build seller order workspace.** **Description:** Present seller-specific preparation and shipment work. **Scope:** Seller portal. **Acceptance Criteria:** Sellers cannot access another seller's order.
- **Task: Add Ready to Ship.** **Description:** Advance paid orders to courier assignment. **Scope:** Seller/courier workflow. **Acceptance Criteria:** Invalid transitions fail and every transition is retained.

#### Story 7.2 - Wallet and payouts

- **Task: Implement seller ledger.** **Description:** Add sale credits, commissions, charges, refunds, and controlled adjustments. **Scope:** Finance domain. **Acceptance Criteria:** Pending/available balances reconcile with ledger entries.
- **Task: Request weekly payouts.** **Description:** Let eligible sellers request and track payouts. **Scope:** Seller wallet. **Acceptance Criteria:** Hold-period and minimum-balance rules are enforced.

## Admin Portal

### Epic 8 - Operations and catalog control

**Description:** Equip operations teams to moderate sellers, catalog, commissions, and auctions.

**Scope:** Dashboards, seller verification, categories, brands, listing moderation, commission rules, auctions, and CMS.

**Acceptance Criteria:** Every moderation action is permission-gated, explained, and audited.

#### Story 8.1 - Operations

- **Task: Build operations dashboard.** **Description:** Show role-appropriate metrics, queues, and exceptions. **Scope:** Admin/Finance home. **Acceptance Criteria:** Metrics separate marketplace, auction, seller, buyer, finance, and logistics signals.
- **Task: Build seller review queue.** **Description:** Approve, reject, suspend, or reactivate sellers with reasons. **Scope:** Seller management. **Acceptance Criteria:** Status immediately controls seller capabilities.

#### Story 8.2 - Catalog and auction moderation

- **Task: Manage categories, brands, and commissions.** **Description:** Maintain hierarchy and effective commission policies. **Scope:** Catalog administration. **Acceptance Criteria:** Invalid rules cannot activate and history remains unchanged.
- **Task: Moderate listings and auctions.** **Description:** Approve, reject, request changes, suspend, archive, and handle auction exceptions. **Scope:** Marketplace operations. **Acceptance Criteria:** Cancellations require a privileged actor and audit reason.

### Epic 9 - Finance, support, reports, and content

**Description:** Complete operational financial and customer-support capability.

**Scope:** Payments, refunds, settlements, returns/disputes, reports, and homepage content.

**Acceptance Criteria:** Every operational record reconciles to underlying transactions and audit events.

#### Story 9.1 - Financial operations

- **Task: Process payments and refunds.** **Description:** Verify bank proofs, inspect Stripe states, and process linked refunds. **Scope:** Finance portal. **Acceptance Criteria:** Refunds create corresponding ledger adjustments.
- **Task: Process settlements.** **Description:** Review, pay, reject, and evidence seller payouts. **Scope:** Finance portal. **Acceptance Criteria:** Only available balances settle and all references are retained.

#### Story 9.2 - Support and reporting

- **Task: Resolve returns and disputes.** **Description:** Review evidence and make return/refund decisions. **Scope:** Admin support. **Acceptance Criteria:** Status, evidence, actor, reason, and notifications are retained.
- **Task: Build reports and CMS.** **Description:** Provide KPIs and control featured content. **Scope:** Admin reporting/marketing. **Acceptance Criteria:** Reports protect private data and provide required marketplace metrics.

## Launch Baseline

Initial editable Electronics configuration is: 8% commission; seven-day auctions; Rs. 500/1,000/2,000 bid tiers; bids in the final five minutes extend five minutes; 48-hour winner payment deadline; Buy Now COD up to Rs. 50,000; seven-day return and settlement hold; Rs. 5,000 payout minimum; verified KYC required. Staging requires approved Stripe credentials, legal/tax/invoicing policy, cancellation policy, shipping-liability policy, backup/restore verification, queue scheduler, and alerting.
