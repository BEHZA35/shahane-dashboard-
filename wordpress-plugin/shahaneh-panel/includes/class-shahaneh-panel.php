<?php
if (!defined('ABSPATH')) { exit; }

final class Shahaneh_Panel {
    private static ?self $instance = null;
    private const REWRITE = 'shahaneh_panel_route';
    private const COOKIE = 'shahaneh_panel_session';
    private const SESSION_TTL = 28800;
    private const OPTION_HASH = 'shahaneh_panel_password_hash';
    private const OPTION_EMAILS = 'shahaneh_panel_allowed_emails';
    private const OPTION_ENABLED = 'shahaneh_panel_enabled';
    private const OPTION_THEME = 'shahaneh_panel_theme';
    private const OPTION_FLUSH = 'shahaneh_panel_rewrite_flush';

    public static function instance(): self {
        if (self::$instance === null) { self::$instance = new self(); }
        return self::$instance;
    }

    public static function activate(): void {
        update_option(self::OPTION_FLUSH, '1', false);
        if (get_option(self::OPTION_ENABLED, null) === null) { add_option(self::OPTION_ENABLED, '1', '', false); }
        if (get_option(self::OPTION_THEME, null) === null) { add_option(self::OPTION_THEME, 'light', '', false); }
    }

    public static function deactivate(): void { flush_rewrite_rules(); }

    public function init(): void {
        add_action('init', [$this, 'register_rewrite_rule'], 10);
        add_filter('query_vars', [$this, 'query_vars']);
        add_action('template_redirect', [$this, 'dispatch'], 0);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_shahaneh_panel_save_settings', [$this, 'save_settings']);
    }

    public function register_rewrite_rule(): void {
        add_rewrite_rule('^panel/?$', 'index.php?' . self::REWRITE . '=1', 'top');
        if (get_option(self::OPTION_FLUSH) === '1') {
            flush_rewrite_rules(false);
            delete_option(self::OPTION_FLUSH);
        }
    }

    public function query_vars(array $vars): array {
        $vars[] = self::REWRITE;
        return $vars;
    }

    public function admin_menu(): void {
        add_options_page('تنظیمات پنل شاهانه', 'پنل شاهانه', 'manage_options', 'shahaneh-panel', [$this, 'settings_page']);
    }

    public function register_settings(): void {
        register_setting('shahaneh_panel_settings', self::OPTION_EMAILS, ['type' => 'string', 'sanitize_callback' => [$this, 'sanitize_emails'], 'default' => '']);
        register_setting('shahaneh_panel_settings', self::OPTION_ENABLED, ['type' => 'string', 'sanitize_callback' => static fn($v) => $v === '1' ? '1' : '0', 'default' => '1']);
        register_setting('shahaneh_panel_settings', self::OPTION_THEME, ['type' => 'string', 'sanitize_callback' => static fn($v) => in_array($v, ['light', 'dark'], true) ? $v : 'light', 'default' => 'light']);
    }

    public function sanitize_emails($value): string {
        $items = preg_split('/[;,\\s]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        $valid = [];
        foreach ($items as $email) {
            $email = sanitize_email($email);
            if ($email && is_email($email)) { $valid[] = strtolower($email); }
        }
        return implode("\n", array_unique($valid));
    }

    public function settings_page(): void {
        if (!current_user_can('manage_options')) { return; }
        $has_password = (bool) get_option(self::OPTION_HASH, '');
        ?>
        <div class="wrap" dir="rtl">
            <h1>تنظیمات پنل شاهانه</h1>
            <?php if (isset($_GET['panel_password_saved'])): ?>
                <div class="notice notice-success is-dismissible"><p>رمز پنل با موفقیت ذخیره شد.</p></div>
            <?php elseif (isset($_GET['panel_password_error'])): ?>
                <div class="notice notice-error is-dismissible"><p>رمز ذخیره نشد؛ باید بین ۱۴ تا ۲۵۶ نویسه باشد.</p></div>
            <?php endif; ?>
            <p>این تنظیمات فقط برای مدیر دارای دسترسی مدیریت وردپرس قابل تغییر است.</p>
            <form method="post" action="options.php">
                <?php settings_fields('shahaneh_panel_settings'); ?>
                <table class="form-table">
                    <tr><th scope="row">فعال‌سازی مسیر پنل</th><td><input type="hidden" name="<?php echo esc_attr(self::OPTION_ENABLED); ?>" value="0"><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_ENABLED); ?>" value="1" <?php checked(get_option(self::OPTION_ENABLED, '1'), '1'); ?>> فعال باشد</label></td></tr>
                    <tr><th scope="row">ایمیل‌های مجاز</th><td><textarea class="large-text" rows="4" name="<?php echo esc_attr(self::OPTION_EMAILS); ?>"><?php echo esc_textarea((string) get_option(self::OPTION_EMAILS, '')); ?></textarea><p class="description">ایمیل حساب مدیر وردپرس را دقیقاً وارد کنید؛ هر ایمیل در یک خط. فهرست خالی به معنی عدم اجازه ورود است.</p></td></tr>
                    <tr><th scope="row">پوسته پیش‌فرض</th><td><select name="<?php echo esc_attr(self::OPTION_THEME); ?>"><option value="light" <?php selected(get_option(self::OPTION_THEME, 'light'), 'light'); ?>>روشن</option><option value="dark" <?php selected(get_option(self::OPTION_THEME, 'light'), 'dark'); ?>>تیره</option></select></td></tr>
                </table>
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
            <hr><h2>رمز مستقل پنل</h2>
            <p><?php echo $has_password ? 'رمز مستقل پنل ذخیره شده است. برای تغییر، رمز جدید را وارد کنید.' : 'هنوز رمز مستقل پنل تعیین نشده است.'; ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="shahaneh_panel_save_settings">
                <?php wp_nonce_field('shahaneh_panel_save_settings'); ?>
                <input type="password" name="panel_password" class="regular-text" minlength="14" maxlength="256" autocomplete="new-password" required>
                <p class="description">حداقل ۱۴ نویسه. رمز به‌صورت هش امن ذخیره می‌شود.</p>
                <?php submit_button('ثبت رمز پنل'); ?>
            </form>
            <p><strong>نشانی پنل:</strong> <a href="<?php echo esc_url(home_url('/panel/')); ?>" target="_blank" rel="noopener"><?php echo esc_html(home_url('/panel/')); ?></a></p>
        </div>
        <?php
    }

    public function save_settings(): void {
        if (!current_user_can('manage_options')) { wp_die('دسترسی غیرمجاز', '', ['response' => 403]); }
        check_admin_referer('shahaneh_panel_save_settings');
        $password = isset($_POST['panel_password']) ? (string) wp_unslash($_POST['panel_password']) : '';
        if (strlen($password) < 14 || strlen($password) > 256) {
            wp_safe_redirect(add_query_arg('panel_password_error', '1', admin_url('options-general.php?page=shahaneh-panel')));
            exit;
        }
        $saved = update_option(self::OPTION_HASH, password_hash($password, PASSWORD_DEFAULT), false);
        if (!$saved && !get_option(self::OPTION_HASH)) {
            wp_safe_redirect(add_query_arg('panel_password_error', '1', admin_url('options-general.php?page=shahaneh-panel')));
            exit;
        }
        $this->clear_session_cookie();
        wp_safe_redirect(add_query_arg('panel_password_saved', '1', admin_url('options-general.php?page=shahaneh-panel')));
        exit;
    }

    public function dispatch(): void {
        if ((int) get_query_var(self::REWRITE) !== 1) { return; }
        if (get_option(self::OPTION_ENABLED, '1') !== '1') { status_header(404); exit; }
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { $this->handle_post(); return; }
        $this->render();
        exit;
    }

    private function handle_post(): void {
        $action = isset($_POST['panel_action']) ? sanitize_key(wp_unslash($_POST['panel_action'])) : '';
        if ($action === 'logout') {
            $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
            if (!$nonce || !wp_verify_nonce($nonce, 'shahaneh_panel_logout')) { status_header(403); exit('درخواست نامعتبر است.'); }
            $this->clear_session_cookie();
            wp_safe_redirect(home_url('/panel/'));
            exit;
        }
        if ($action !== 'login') { status_header(400); exit('درخواست نامعتبر است.'); }
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'shahaneh_panel_login')) { status_header(403); $this->render('فرم منقضی شده است؛ صفحه را تازه‌سازی کنید.'); exit; }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
        $rate_key = 'shp_login_' . hash_hmac('sha256', $ip, wp_salt('auth'));
        $attempts = (int) get_transient($rate_key);
        if ($attempts >= 6) { status_header(429); $this->render('تعداد تلاش‌ها زیاد است؛ ۱۵ دقیقه دیگر دوباره تلاش کنید.'); exit; }

        $email = isset($_POST['panel_email']) ? strtolower(sanitize_email(wp_unslash($_POST['panel_email']))) : '';
        $password = isset($_POST['panel_password']) ? (string) wp_unslash($_POST['panel_password']) : '';
        $hash = (string) get_option(self::OPTION_HASH, '');
        $user = $email && is_email($email) ? get_user_by('email', $email) : false;
        $authorized = $user && $this->allowed_admin_id((int) $user->ID);

        if (!$authorized || !$hash || !password_verify($password, $hash)) {
            set_transient($rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
            status_header(401);
            $this->render('ایمیل یا رمز پنل صحیح نیست، یا حساب شما در فهرست مدیران مجاز نیست.');
            exit;
        }

        delete_transient($rate_key);
        $this->set_session_cookie((int) $user->ID);
        wp_safe_redirect(home_url('/panel/'));
        exit;
    }

    private function allowed_admin_id(int $user_id): bool {
        $user = get_user_by('id', $user_id);
        if (!$user || !user_can($user, 'manage_options')) { return false; }
        $allowed = array_filter(array_map('trim', explode("\n", (string) get_option(self::OPTION_EMAILS, ''))));
        return in_array(strtolower((string) $user->user_email), array_map('strtolower', $allowed), true);
    }

    private function session_payload(int $user_id): string {
        $expires = (string) (time() + self::SESSION_TTL);
        $data = $user_id . '|' . $expires . '|' . bin2hex(random_bytes(16));
        return base64_encode($data . '|' . hash_hmac('sha256', $data, wp_salt('auth')));
    }

    private function session_user_id(): int {
        if (empty($_COOKIE[self::COOKIE])) { return 0; }
        $raw = base64_decode((string) wp_unslash($_COOKIE[self::COOKIE]), true);
        if (!$raw) { return 0; }
        $parts = explode('|', $raw);
        if (count($parts) !== 4) { return 0; }
        [$user, $expires, $nonce, $signature] = $parts;
        if (!ctype_digit($user) || !ctype_digit($expires) || (int) $expires < time()) { return 0; }
        $data = $user . '|' . $expires . '|' . $nonce;
        if (!hash_equals(hash_hmac('sha256', $data, wp_salt('auth')), $signature)) { return 0; }
        return $this->allowed_admin_id((int) $user) ? (int) $user : 0;
    }

    private function set_session_cookie(int $user_id): void {
        if (!$this->allowed_admin_id($user_id)) { return; }
        setcookie(self::COOKIE, $this->session_payload($user_id), [
            'expires' => time() + self::SESSION_TTL,
            'path' => defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/',
            'domain' => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
            'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax'
        ]);
    }

    private function clear_session_cookie(): void {
        setcookie(self::COOKIE, '', [
            'expires' => time() - 3600,
            'path' => defined('COOKIEPATH') && COOKIEPATH ? COOKIEPATH : '/',
            'domain' => defined('COOKIE_DOMAIN') ? COOKIE_DOMAIN : '',
            'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax'
        ]);
        unset($_COOKIE[self::COOKIE]);
    }

    private function render(string $error = ''): void {
        $user_id = $this->session_user_id();
        $authenticated = $user_id > 0;
        $password_ready = (bool) get_option(self::OPTION_HASH, '');
        $logo_id = (int) get_theme_mod('custom_logo');
        $logo_url = $logo_id ? wp_get_attachment_image_url($logo_id, 'full') : '';
        $site_name = get_bloginfo('name') ?: 'شاهانه';
        status_header(200);
        nocache_headers();
        ?><!doctype html>
        <html lang="fa" dir="rtl"><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>پنل شاهانه</title><link rel="stylesheet" href="<?php echo esc_url(SHAHANEH_PANEL_URL . 'assets/panel.css?ver=' . SHAHANEH_PANEL_VERSION); ?>"></head>
        <body class="shp-body <?php echo $authenticated ? 'shp-authenticated' : 'shp-guest'; ?>">
        <div class="shp-app">
            <aside class="shp-sidebar">
                <div class="shp-brand">
                    <div class="shp-brand-logo"><?php if ($logo_url): ?><img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>"><?php else: ?><span class="shp-crown">♛</span><?php endif; ?></div>
                    <div class="shp-brand-copy"><strong>شاهانه</strong><small>SHAHANEH COMMERCE</small></div>
                    <span class="shp-brand-status" title="پنل اختصاصی"></span>
                </div>
                <?php if ($authenticated): ?>
                <div class="shp-store-switch"><span class="shp-store-avatar">ش</span><span><b>فروشگاه شاهانه</b><small>مدیریت فروشگاه</small></span><span class="shp-chevron">⌄</span></div>
                <div class="shp-nav-label">مدیریت فروشگاه</div>
                <nav class="shp-nav" aria-label="منوی پنل">
                    <a class="shp-nav-item is-active" href="<?php echo esc_url(home_url('/panel/')); ?>"><span class="shp-nav-icon">⌂</span><span>داشبورد</span><em>نمای کلی</em></a>
                    <div class="shp-nav-item is-disabled"><span class="shp-nav-icon">▦</span><span>محصولات</span><small>در حال توسعه</small></div>
                    <div class="shp-nav-item is-disabled"><span class="shp-nav-icon">◇</span><span>قیمت دوم</span><small>در حال توسعه</small></div>
                    <div class="shp-nav-item is-disabled"><span class="shp-nav-icon">▤</span><span>دسته‌بندی‌ها</span><small>در حال توسعه</small></div>
                    <div class="shp-nav-item is-disabled"><span class="shp-nav-icon">▣</span><span>سفارش‌ها</span><small>در حال توسعه</small></div>
                    <div class="shp-nav-item is-disabled"><span class="shp-nav-icon">♙</span><span>مشتریان</span><small>در حال توسعه</small></div>
                    <div class="shp-nav-item is-disabled"><span class="shp-nav-icon">▥</span><span>انبار و موجودی</span><small>در حال توسعه</small></div>
                    <div class="shp-nav-item is-disabled"><span class="shp-nav-icon">%</span><span>تخفیف‌ها</span><small>در حال توسعه</small></div>
                    <div class="shp-nav-label shp-nav-label-spaced">رشد و ابزارها</div>
                    <div class="shp-nav-item is-disabled"><span class="shp-nav-icon">▧</span><span>گزارش‌ها و مالی</span><small>در حال توسعه</small></div>
                    <div class="shp-nav-item is-disabled"><span class="shp-nav-icon">✉</span><span>پیامک و اتوماسیون</span><small>در حال توسعه</small></div>
                    <div class="shp-nav-item is-disabled"><span class="shp-nav-icon">✧</span><span>دستیار هوشمند</span><small>در حال توسعه</small></div>
                    <div class="shp-nav-label shp-nav-label-spaced">مدیریت سیستم</div>
                    <div class="shp-nav-item is-disabled"><span class="shp-nav-icon">⚙</span><span>تنظیمات پنل</span><small>در حال توسعه</small></div>
                    <div class="shp-nav-item is-disabled"><span class="shp-nav-icon">♧</span><span>امنیت و کاربران</span><small>در حال توسعه</small></div>
                </nav>
                <div class="shp-sidebar-bottom"><div class="shp-help-orb">?</div><div><b>به کمک نیاز دارید؟</b><small>مرکز راهنمای شاهانه</small></div></div>
                <?php else: ?>
                <div class="shp-sidebar-art"><div class="shp-art-orbit shp-orbit-one"></div><div class="shp-art-orbit shp-orbit-two"></div><div class="shp-art-gem">♛</div><span>SHAHANEH</span></div>
                <div class="shp-sidebar-caption">مدیریت هوشمند فروشگاه،<br>با هویت اختصاصی شاهانه.</div>
                <?php endif; ?>
                <div class="shp-sidebar-footer"><span class="shp-online-dot"></span> سیستم پنل فعال است <small>v<?php echo esc_html(SHAHANEH_PANEL_VERSION); ?></small></div>
            </aside>
            <main class="shp-main">
                <header class="shp-topbar">
                    <div class="shp-topbar-title"><span class="shp-breadcrumb">شاهانه <i>/</i> <?php echo $authenticated ? 'مدیریت فروشگاه' : 'ورود امن'; ?></span><h1><?php echo $authenticated ? 'داشبورد' : 'خوش آمدید'; ?></h1></div>
                    <div class="shp-topbar-actions">
                        <span class="shp-top-date"><b>امروز</b><?php echo esc_html(wp_date('l، j F')); ?></span>
                        <?php if ($authenticated): ?>
                        <span class="shp-top-avatar"><?php echo esc_html(mb_substr((string) (get_user_by('id', $user_id)->display_name ?? 'ش'), 0, 1)); ?></span>
                        <form method="post" class="shp-logout-form"><input type="hidden" name="panel_action" value="logout"><?php wp_nonce_field('shahaneh_panel_logout'); ?><button class="shp-logout" type="submit"><span>↪</span> خروج</button></form>
                        <?php endif; ?>
                    </div>
                </header>
                <?php if (!$password_ready): ?>
                    <section class="shp-card shp-setup-card"><div class="shp-setup-icon">✦</div><span class="shp-eyebrow">راه‌اندازی اولیه</span><h2>یک قدم تا شروع مدیریت شاهانه</h2><p>ابتدا با حساب مدیر وردپرس وارد شوید و از مسیر «تنظیمات ← پنل شاهانه» رمز مستقل پنل را تعیین کنید.</p><a class="shp-primary-link" href="<?php echo esc_url(admin_url('options-general.php?page=shahaneh-panel')); ?>">تنظیم رمز مستقل پنل <span>←</span></a></section>
                <?php elseif (!$authenticated): ?>
                    <section class="shp-login-layout">
                        <div class="shp-login-intro"><span class="shp-eyebrow">SHAHANEH PRIVATE WORKSPACE</span><h2>همه چیز برای<br><em>رشد شاهانه</em></h2><p>به فضای مدیریت اختصاصی فروشگاه وارد شوید و تمام امور فروشگاه را در یک محیط امن و یکپارچه مدیریت کنید.</p><div class="shp-login-benefits"><span>✦ مدیریت یکپارچه فروشگاه</span><span>✦ دسترسی امن و اختصاصی</span><span>✦ تجربه فارسی و هوشمند</span></div></div>
                        <div class="shp-login-card"><div class="shp-login-card-head"><div class="shp-login-lock">♙</div><span class="shp-eyebrow">SECURE ADMIN ACCESS</span><h2>ورود به پنل شاهانه</h2><p>اطلاعات حساب مدیریتی خود را وارد کنید.</p></div>
                        <?php if ($error): ?><div class="shp-error" role="alert"><?php echo esc_html($error); ?></div><?php endif; ?>
                        <form method="post" class="shp-login-form"><input type="hidden" name="panel_action" value="login"><?php wp_nonce_field('shahaneh_panel_login'); ?><label for="panel_email">ایمیل مدیر مجاز</label><div class="shp-input-wrap"><span>✉</span><input id="panel_email" name="panel_email" type="email" autocomplete="username" placeholder="name@example.com" required></div><label for="panel_password">رمز مستقل پنل</label><div class="shp-input-wrap"><span>◇</span><input id="panel_password" name="panel_password" type="password" autocomplete="current-password" placeholder="رمز عبور اختصاصی" required></div><button type="submit" class="shp-login-submit">ورود امن به پنل <span>←</span></button></form><small class="shp-login-foot">این رمز مستقل از رمز ورود وردپرس است.</small></div>
                    </section>
                <?php else: ?>
                    <div class="shp-welcome-strip"><div class="shp-welcome-symbol">✦</div><div><span class="shp-eyebrow">SHAHANEH CONTROL CENTER</span><h2>خوش آمدید، <?php echo esc_html(get_user_by('id', $user_id)->display_name ?? 'مدیر شاهانه'); ?></h2><p>نمایی روشن از وضعیت امروز فروشگاه شما؛ همه‌چیز برای یک روز پرفروش آماده است.</p></div><div class="shp-welcome-decoration" aria-hidden="true"><i></i><b></b><span></span></div></div>
                    <?php Shahaneh_Dashboard::render($user_id); ?>
                <?php endif; ?>
                <footer class="shp-footer"><span>شاهانه</span> · پنل مدیریت اختصاصی <i>◆</i> <?php echo esc_html(wp_date('Y')); ?></footer>
            </main>
        </div></body></html><?php
    }
}
