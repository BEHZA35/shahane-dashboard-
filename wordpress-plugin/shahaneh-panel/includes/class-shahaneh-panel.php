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
        status_header(200);
        nocache_headers();
        ?><!doctype html>
        <html lang="fa" dir="rtl"><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title>پنل شاهانه</title><link rel="stylesheet" href="<?php echo esc_url(includes_url('css/dashicons.min.css')); ?>"><link rel="stylesheet" href="<?php echo esc_url(SHAHANEH_PANEL_URL . 'assets/panel.css?ver=' . SHAHANEH_PANEL_VERSION); ?>"></head>
        <body class="shp-body"><div class="shp-shell">
        <aside class="shp-side"><div class="shp-brand"><span>♛</span><div><b>شاهانه</b><small>SHAHANEH PANEL</small></div></div><p>مدیریت فروشگاه</p><nav aria-label="منوی مدیریت شاهانه">
        <a href="<?php echo esc_url(home_url('/panel/')); ?>"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 3h8v8H3z M13 3h8v5h-8z M13 10h8v11h-8z M3 13h8v8H3z"/></svg><span>داشبورد</span></a>
        <span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 7V5a6 6 0 0 1 12 0v2 M3 7h18l-1 14H4L3 7z"/></svg><span>محصولات</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M20 13 13 20 3 10V3h7l10 10z M7.5 7.5h.01"/></svg><span>قیمت دوم</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 4h8v7H3z M13 4h8v7h-8z M3 13h8v7H3z M13 13h8v7h-8z"/></svg><span>دسته‌بندی‌ها</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 3h2l2.4 12.2a2 2 0 0 0 2 1.6h8.8a2 2 0 0 0 2-1.6L22 8H6 M10 21h.01 M18 21h.01"/></svg><span>سفارش‌ها</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2 M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8 M20 8a3 3 0 0 0-2-2.8 M20 14a4 4 0 0 1 2 3.5V21"/></svg><span>مشتریان</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 4h18v4H3z M5 8v12h14V8 M9 12h6"/></svg><span>انبار و موجودی</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 7h18v4a2 2 0 0 0 0 4v4H3v-4a2 2 0 0 0 0-4V7z M13 7v2 M13 13v2 M13 18v1"/></svg><span>تخفیف‌ها</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 19V9 M10 19V4 M16 19v-7 M22 19H2"/></svg><span>گزارش‌ها و مالی</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 4h18v16H3z M7 15l3-3 3 3 2-2 3 3 M8 8h.01"/></svg><span>رسانه و تصاویر</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 5h18v14H3z M3 6l9 7 9-7"/></svg><span>پیامک و اطلاع‌رسانی</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.8 1.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5v.1h-2.6v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-1.8-1.8.1-.1A1.7 1.7 0 0 0 8 15a1.7 1.7 0 0 0-1.5-1H6.4v-2.6h.1A1.7 1.7 0 0 0 8 10a1.7 1.7 0 0 0-.3-1.9l-.1-.1 1.8-1.8.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5V5h2.6v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 1.8 1.8-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1v2.6h-.1a1.7 1.7 0 0 0-1.5 1.4z"/></svg><span>اتوماسیون</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M8 3h8v5a4 4 0 0 1-8 0V3z M8 5H4v2a4 4 0 0 0 4 4 M16 5h4v2a4 4 0 0 1-4 4 M12 12v5 M8 21h8 M10 17h4"/></svg><span>باشگاه مشتریان</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 17l6-6 4 3 8-9 M15 5h6v6"/></svg><span>سئو و رشد فروشگاه</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m12 3 2.7 5.5 6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9L12 3z"/></svg><span>دستیار هوش مصنوعی</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11z M9 12l2 2 4-4"/></svg><span>کاربران و دسترسی</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9 M10 21h4"/></svg><span>اعلان‌ها</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 10h14v11H5z M8 10V7a4 4 0 0 1 8 0v3"/></svg><span>امنیت و فعالیت‌ها</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3c5 0 9 1.6 9 3.5S17 10 12 10 3 8.4 3 6.5 7 3 12 3z M3 6.5v11c0 2 4 3.5 9 3.5 M21 6.5v6 M3 12c0 2 4 3.5 9 3.5 M15 18h7 M19 14l4 4-4 4"/></svg><span>پشتیبان‌گیری</span></span><span class="shp-nav-item" role="button" tabindex="0"><svg class="shp-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-1.8 1.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5v.1h-2.6v-.1a1.7 1.7 0 0 0-1-1.5 1.7 1.7 0 0 0-1.9.3l-.1.1-1.8-1.8.1-.1A1.7 1.7 0 0 0 8 15a1.7 1.7 0 0 0-1.5-1H6.4v-2.6h.1A1.7 1.7 0 0 0 8 10a1.7 1.7 0 0 0-.3-1.9l-.1-.1 1.8-1.8.1.1a1.7 1.7 0 0 0 1.9.3 1.7 1.7 0 0 0 1-1.5V5h2.6v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1 1.8 1.8-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.5 1h.1v2.6h-.1a1.7 1.7 0 0 0-1.5 1.4z"/></svg><span>تنظیمات عمومی</span></span>
        </nav><small class="shp-side-foot">شاهانه · نسخه توسعه</small></aside>
        <main class="shp-main"><header><div class="shp-header-user"><span class="shp-avatar">ش</span><div><small><?php echo $authenticated ? esc_html(wp_get_current_user()->user_login) : 'مدیریت فروشگاه'; ?></small><b>مدیر کل</b></div></div><div class="shp-header-title"><small>SHAHANEH · پنل مدیریت اختصاصی</small><h1><?php echo $authenticated ? 'داشبورد مدیریت شاهانه' : 'ورود امن به پنل شاهانه'; ?></h1></div><div class="shp-header-tools"><span class="shp-header-tool">♧</span><span class="shp-header-tool">☾</span><span class="shp-header-date">▦ <?php echo esc_html(wp_date('l، j F Y')); ?></span></div>
        <?php if ($authenticated): ?><form method="post"><input type="hidden" name="panel_action" value="logout"><?php wp_nonce_field('shahaneh_panel_logout'); ?><button class="shp-logout">خروج امن</button></form><?php endif; ?></header>
        <?php if (!$password_ready): ?>
            <section class="shp-card"><h2>راه‌اندازی اولیه</h2><p>ابتدا با حساب مدیر وردپرس وارد شوید و از مسیر «تنظیمات ← پنل شاهانه» رمز مستقل پنل را تعیین کنید.</p><a href="<?php echo esc_url(admin_url('options-general.php?page=shahaneh-panel')); ?>">رفتن به تنظیمات پنل</a></section>
        <?php elseif (!$authenticated): ?>
            <section class="shp-login"><div class="shp-emblem">ش</div><h2>ورود مدیر</h2><p>ایمیل مدیر مجاز و رمز مستقل پنل را وارد کنید.</p>
            <?php if ($error): ?><div class="shp-error"><?php echo esc_html($error); ?></div><?php endif; ?>
            <form method="post"><input type="hidden" name="panel_action" value="login"><?php wp_nonce_field('shahaneh_panel_login'); ?><label for="panel_email">ایمیل مدیر مجاز</label><input id="panel_email" name="panel_email" type="email" autocomplete="username" required><label for="panel_password">رمز مستقل پنل</label><input id="panel_password" name="panel_password" type="password" autocomplete="current-password" required><button type="submit">ورود به پنل</button></form></section>
        <?php else: $panel_user = get_user_by('id', $user_id); ?>
            <?php Shahaneh_Dashboard::render($user_id); ?>
        <?php endif; ?>
        <footer>شاهانه · پنل مدیریت اختصاصی · <?php echo esc_html(wp_date('Y')); ?></footer></main></div><script>
(function(){
 var nav=document.querySelector('.shp-side nav'); if(!nav)return;
 var items=nav.querySelectorAll('a, .shp-nav-item');
 function select(item){
  items.forEach(function(el){el.classList.remove('active','is-selected');el.removeAttribute('aria-current');});
  item.classList.add('active','is-selected');item.setAttribute('aria-current','page');
  try{localStorage.setItem('shp_selected_menu',String(Array.prototype.indexOf.call(items,item)));}catch(e){}
 }
 nav.addEventListener('click',function(e){
  var item=e.target.closest('a, .shp-nav-item');if(!item||!nav.contains(item))return;
  if(item.classList.contains('shp-nav-item'))e.preventDefault();
  select(item);
 },true);
 nav.addEventListener('keydown',function(e){
  var item=e.target.closest('.shp-nav-item');
  if(item&&(e.key==='Enter'||e.key===' ')){e.preventDefault();select(item);}
 });
 try{
  var saved=parseInt(localStorage.getItem('shp_selected_menu'),10);
  if(!isNaN(saved)&&items[saved])select(items[saved]);
  else {localStorage.removeItem('shp_selected_menu');}
 }catch(e){}
})();
</script></body></html><?php
    }
}
