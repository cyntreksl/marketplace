---
paths:
  - bootstrap/app.php
---

# Bootstrap

## Cap Vite HTTP preload links
Keep AddLinkHeadersForPreloadedAssets capped at five assets. Dependency-heavy Inertia pages can otherwise exceed Nginx/FastCGI response-header buffers and return 502 on a direct page load while client-side visits still work.

## Keep Meta attribution cookies plaintext
Keep `_fbc` and `_fbp` in Laravel's cookie-encryption exception list. Meta creates these as plaintext browser cookies, and encrypting or decrypting them prevents the server-side Conversions API flow from reading valid values.
