# Phase 2 — Live WooCommerce dashboard

Branch: `phase-1-plugin-core`

## Implemented
- [x] Read-only dashboard module loaded by the WordPress plugin.
- [x] WooCommerce availability check; clear notice when WooCommerce is inactive.
- [x] Today's gross order total from processing and completed orders.
- [x] Current month's gross order total from processing and completed orders.
- [x] Pending, processing, and completed order counts using WooCommerce order-count API.
- [x] Published product count.
- [x] Eight latest orders with customer, total, status, and date.
- [x] Low-stock product list using each product's low-stock threshold (or WooCommerce default).
- [x] Responsive metric cards, order table, and stock alert styling.
- [x] No write operations or changes to products/orders/customers.
- [x] Explicit note that sales totals are gross order totals, not net profit.

## Important behavior
- Data is read directly from the WordPress/WooCommerce installation where this plugin is active. When installed in LocalWP, it displays the LocalWP database, not the live website.
- Today's and monthly sales include orders with processing and completed statuses only. Pending, failed, cancelled, refunded, and on-hold orders are excluded from these gross sales totals.
- Metrics are queried server-side; WooCommerce credentials are not exposed in browser JavaScript.
- This first dashboard iteration does not yet include date-range controls, chart visualizations, refunds, net revenue, shipping/tax breakdown, or cost-based profit.

## Required LocalWP acceptance tests
- [ ] Confirm WooCommerce is active.
- [ ] Confirm totals match WooCommerce Analytics/Orders for the same statuses and time window.
- [ ] Confirm recent order table matches the latest orders.
- [ ] Create or edit a low-stock test product and verify it appears in the alert list.
- [ ] Test desktop and mobile layouts.
- [ ] Verify panel login/session still works after refresh and logout still clears access.
- [ ] Run PHP lint and WordPress security/code checks on the installed plugin.
- [ ] Do not deploy to shahanee.com before explicit user approval.
