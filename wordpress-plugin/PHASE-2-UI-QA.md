# Shahaneh Panel — Independent UI shell (Phase 2 UI)

## Scope in this branch
- Reworked the public-facing `/panel` route into a standalone, RTL admin workspace.
- Added premium Shahaneh visual system: petroleum-green sidebar, teal accents, orange primary action, soft gold highlights, bright workspace, layered cards and restrained CSS 3D effects.
- Uses the site's configured WordPress custom logo when available; otherwise shows a Shahaneh crown monogram.
- Added responsive navigation layout and dedicated glass-inspired login screen.
- Preserved the existing independent panel password/session flow and the live, read-only WooCommerce dashboard.
- Unimplemented modules are visibly marked "در حال توسعه" and are not presented as active buttons.
- Bumped plugin version to 0.2.0 to refresh browser asset cache.

## Not implemented in this UI branch
- The 20 modules from the project specification are not yet complete.
- No new product/order write operations, Google OAuth, SMS OTP, AI actions, customer loyalty, backups, advanced reports, or user-permission editor are claimed.
- Sidebar items other than Dashboard are informational disabled entries until their modules are implemented.

## Required LocalWP acceptance tests
1. Install/replace plugin files on `http://shahanee-test.local` and confirm plugin activates.
2. Open `/panel` directly while logged out; confirm custom login screen appears.
3. Log in with existing allowed admin email and independent panel password.
4. Confirm existing WooCommerce metrics, recent orders and low-stock data still display.
5. Confirm logout returns to `/panel` login.
6. Check desktop, tablet and mobile widths; ensure no horizontal overflow.
7. Check configured site logo appears; if no custom logo is configured, confirm crown fallback.
8. Check WordPress admin pages, theme and WooCommerce storefront remain unaffected.

## QA status
- GitHub branch changes committed.
- Automated PHP lint and browser testing have not yet been run in this environment.
- Do not merge or deploy to the live site until LocalWP acceptance tests pass and the user approves.
