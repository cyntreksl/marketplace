---
paths:
  - 'app/Services/**'
---

# Services

## Approve typed listing brands with listing moderation
Seller-entered brand text stays on the private listing as brand_name. Only approved listings resolve it to an existing or newly created catalog Brand inside the moderation transaction, with an audit entry.

## Synchronize permitted Google taxonomy categories
Activating a Google taxonomy version must synchronize permitted nodes into marketplace categories in one transaction. Excluded IDs and descendants stay reference-only; preserve mapped category slugs, commercial settings, and listing links; deactivate removed mappings without deleting them; leave categories without Google IDs untouched.
