=== Shahaneh Admin Panel ===
Contributors: shahaneh
Tags: woocommerce, admin, rtl, dashboard
Requires at least: 6.2
Requires PHP: 8.0
Stable tag: 0.1.0
License: GPLv2 or later

Secure RTL administration panel foundation for Shahaneh.

== Installation ==
1. Upload the shahaneh-panel ZIP through Plugins > Add New > Upload Plugin.
2. Activate the plugin.
3. Sign in to WordPress as an administrator.
4. Open Settings > پنل شاهانه and set a strong independent panel password (minimum 14 characters).
5. Optionally configure the allowed administrator email list.
6. Visit /panel.

== Security notes ==
The panel requires an authenticated WordPress administrator and a separate panel password. The separate password is stored as a password hash. Session cookies are HttpOnly, signed, time-limited, and SameSite=Strict. Login attempts are throttled by client IP hash. The panel does not elevate roles and does not expose WooCommerce data in this phase.

== Current scope ==
Phase 1 foundation only. WooCommerce CRUD, OAuth, SMS OTP, reports, and operational modules are not yet implemented.
