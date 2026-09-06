---
paths:
  - 'resources/**'
---

# Resources

## Shared storefront spacing and readable commerce typography
Use the storefront-container utility for storefront page content, shared header/footer containers, and every checkout step. It preserves the home page's 82rem maximum width and 16px mobile / 24px sm-and-up horizontal gutters; keep narrower reading/form widths inside it. Product details and checkout use text-sm (14px) for supporting copy and labels, text-base (16px) for body copy and inputs, and larger headings; avoid 10–12px commerce copy. Keep shared product cards consistent with the home page.

## Keep React components out of the Inertia bootstrap
Declare layouts and other React components in separate modules, not resources/js/app.tsx. Vite React Refresh can inject a timestamped self-import for a component-bearing entrypoint, running createInertiaApp twice and leaving stale cart dialogs mounted. Run node --test tests/app-bootstrap.test.mjs when changing bootstrap composition.
