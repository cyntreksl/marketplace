---
paths:
  - bootstrap/app.php
---

# Bootstrap

## Cap Vite HTTP preload links
Keep AddLinkHeadersForPreloadedAssets capped at five assets. Dependency-heavy Inertia pages can otherwise exceed Nginx/FastCGI response-header buffers and return 502 on a direct page load while client-side visits still work.
