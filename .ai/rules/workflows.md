---
paths:
  - '.github/deploy/**,.github/workflows/**'
---

# Workflows

## Keep production SSR web-only and releases aligned
Run Inertia SSR only on the web host as deploy, bound to 127.0.0.1:13714. The worker host runs Supervisor queues only. Production deployments must start from matching release SHAs and roll both hosts back to the same captured SHA on failure.
