---
paths:
  - resources/js/components/seo-head.tsx
---

# Components

## Keep Inertia Head children as native head elements
Do not place React fragments directly inside Inertia Head. In @inertiajs/react 3.7, SSR serializes fragments as Symbol(react.fragment), causing the HTML parser to move later JSON-LD into body and hydration to duplicate Product graphs. Render conditional meta elements individually. Run node --test tests/seo-head.test.mjs and check hydrated DOM for one graph per entity.
