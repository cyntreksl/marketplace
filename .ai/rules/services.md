---
paths:
  - 'app/Services/**'
---

# Services

## Approve typed listing brands with listing moderation
Seller-entered brand text stays on the private listing as brand_name. Only approved listings resolve it to an existing or newly created catalog Brand inside the moderation transaction, with an audit entry.
