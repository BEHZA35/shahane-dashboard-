# Phase 1 — QA and acceptance checklist

Branch: `phase-1-plugin-core`

## Implemented in this branch
- [x] WordPress plugin entry point and activation/deactivation hooks.
- [x] Pretty route registration for `/panel`.
- [x] RTL responsive panel shell with Shahaneh palette.
- [x] Admin settings screen for allowed administrator emails and theme preference.
- [x] Separate panel password hash (PHP `password_hash` / `password_verify`).
- [x] Nonce checks on login/logout and settings password action.
- [x] Login attempt throttling and signed HttpOnly, SameSite=Strict cookie.
- [x] No demo WooCommerce data is displayed as live data.

## Known gaps — do not treat Phase 1 as accepted
- [ ] Authentication currently also requires a logged-in WordPress administrator; the independent panel session requirement needs a final design/implementation pass.
- [ ] Add automated PHP lint and WordPress coding/security checks.
- [ ] Test rewrite flushing, cookie behavior behind HTTPS/proxies, and login throttling on LocalWP.
- [ ] Test role/email allowlist edge cases and unchecked settings persistence.
- [ ] Produce and inspect an installable ZIP; verify its internal folder structure.
- [ ] Test install/activate/deactivate/uninstall on LocalWP at `http://shahanee-test.local`.
- [ ] User acceptance before any production deployment.

## Safety boundary
No changes have been made to the live Shahaneh website. Do not install this branch on production. WooCommerce live data, SMS OTP, Google OAuth, product/order operations, and the remaining modules are out of scope for this phase.
