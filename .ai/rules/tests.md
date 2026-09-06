---
paths:
  - 'tests/**'
---

# Tests

## Keep test media and external services isolated
Upload tests must fake and assert against the r2 disk: phpunit.xml fixes MEDIA_DISK=r2 to match runtime storage. Keep public-disk fakes only for explicit legacy media fixtures or migration sources. Tests\TestCase disables Inertia SSR so a running local Vite server cannot make external requests during feature tests. Stripe credentials are empty in phpunit.xml; payment tests opt in with explicit fake configuration.
