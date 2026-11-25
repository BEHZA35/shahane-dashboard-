<?php
if (!defined('ABSPATH')) { exit; }
final class Shahaneh_Panel {
    private static ?self $instance = null;
    private const REWRITE = 'shahaneh_panel_route';
    private const COOKIE = 'shahaneh_panel_session';
    private const SESSION_TTL = 8 * HOUR_IN_SECONDS;
    private const OPTION_HASH = 'shahaneh_panel_password_hash';
    private const OPTION_EMAILS = 'shahaneh_panel_allowed_emails';
    private const OPTION_ENABLED = 'shahaneh_panel_enabled';
    private const OPTION_THEME = 'shahaneh_panel_theme';

    public static function instance(): self {
        if (self::$instance === null) { self::$instance = new self(); }
        return self::$instance;
    }
    public static function activate(): void {
        add_rewrite_rule('^panel/?$', 'index.php?' . self::REWRITE . '=1', 'top');
        flush_rewrite_rules();
        if (get_option(self::OPTION_ENABLED, null) === null) { add_option(self::OPTION_ENABLED, '1', '', false); }
        if (get_option(self::OPTION_THEME, null) === null) { add_option(self::OPTION_THEME, 'light', '', false); }
    }
    public static function deactivate(): void {
        flush_rewrite_rules();
    }
    public function init(): void {
        add_rewrite_rule('^panel/?$', 'index.php?' . self::REWRITE . '=1', 'top');
        add_filter('query_vars', [$this, 'query_vars']);
        add_action('template_redirect', [$this, 'dispatch'], 0);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_post_shahaneh_panel_save_settings', [$this, 'save_settings']);
    }
    public function query_vars(array $vars): array {
        $vars[] = self::REWRITE;
        return $vars;
    }
    public function admin_menu(): void {
        add_options_page('تنظیمات پنل شاهانه', 'پنل شاهانه', 'manage_options', 'shahaneh-panel', [$this, 'settings_page']);
    }
    public function register_settings(): void {
        register_setting('shahaneh_panel_settings', self::OPTION_EMAILS, [
            'type' => 'string', 'sanitize_callback' => [$this, 'sanitize_emails'], 'default' => ''
        ]);
        register_setting('shahaneh_panel_settings', self::OPTION_ENABLED, [
            'type' => 'string', 'sanitize_callback' => static fn($v) => $v === '1' ? '1' : '0', 'default' => '1'
        ]);
        register_setting('shahaneh_panel_settings', self::OPTION_THEME, [
            'type' => 'string', 'sanitize_callback' => static fn($v) => in_array($v, ['light','dark'], true) ? $v : 'light', 'default' => 'light'
        ]);
    }
    public function sanitize_emails($value): string {
        $items = preg_split('/[;,\s]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
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
            <p>این تنظیمات فقط برای مدیر دارای دسترسی مدیریت وردپرس قابل تغییر است.</p>
            <form method="post" action="options.php">
                <?php settings_fields('shahaneh_panel_settings'); ?>
                <table class="form-table">
                    <tr><th scope="row">فعال‌سازی مسیر پنل</th><td><input type="hidden" name="<?php echo esc_attr(self::OPTION_ENABLED); ?>" value="0"><label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_ENABLED); ?>" value="1" <?php checked(get_option(self::OPTION_ENABLED, '1'), '1'); ?>> فعال باشد</label></td></tr>
                    <tr><th scope="row">ایمیل‌های مجاز</th><td><textarea class="large-text" rows="4" name="<?php echo esc_attr(self::OPTION_EMAILS); ?>"><?php echo esc_textarea((string)get_option(self::OPTION_EMAILS, '')); ?></textarea><p class="description">ایمیل مدیران مجاز؛ هر ایمیل در یک خط. خالی بودن فهرست یعنی فقط کاربران دارای manage_options مجازند.</p></td></tr>
                    <tr><th scope="row">پوسته پیش‌فرض</th><td><select name="<?php echo esc_attr(self::OPTION_THEME); ?>"><option value="light" <?php selected(get_option(self::OPTION_THEME, 'light'), 'light'); ?>>روشن</option><option value="dark" <?php selected(get_option(self::OPTION_THEME, 'light'), 'dark'); ?>>تیره</option></select></td></tr>
                </table>
                <?php submit_button('ذخیره تنظیمات'); ?>
            </form>
            <hr><h2>رمز مستقل پنل</h2>
            <p><?php echo $has_password ? 'رمز مستقل پنل تنظیم شده است. برای جایگزینی، رمز جدید را وارد کنید.' : 'هنوز رمز مستقل پنل تنظیم نشده است. ابتدا آن را تعیین کنید.'; ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="shahaneh_panel_save_settings">
                <?php wp_nonce_field('shahaneh_panel_save_settings'); ?>
                <input type="password" name="panel_password" class="regular-text" minlength="14" autocomplete="new-password" required>
                <p class="description">حداقل ۱۴ نویسه. رمز فقط به‌صورت هش امن ذخیره می‌شود.</p>
                <?php submit_button('ثبت رمز پنل'); ?>
            </form>
            <p><strong>نشانی پنل:</strong> <a href="<?php echo esc_url(home_url('/panel')); ?>" target="_blank" rel="noopener"><?php echo esc_html(home_url('/panel')); ?></a></p>
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
        update_option(self::OPTION_HASH, password_hash($password, PASSWORD_DEFAULT), false);
        $this->clear_session_cookie();
        wp_safe_redirect(add_query_arg('panel_password_saved', '1', admin_url('options-general.php?page=shahaneh-panel')));
        exit;
    }
    public function dispatch(): void {
        if ((int) get_query_var(self::REWRITE) !== 1) { return; }
        if (get_option(self::OPTION_ENABLED, '1') !== '1') { status_header(404); exit; }
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $this->handle_post();
            return;
        }
        $this->render();
        exit;
    }
    private function handle_post(): void {
        $action = isset($_POST['panel_action']) ? sanitize_key(wp_unslash($_POST['panel_action'])) : '';
        if ($action === 'logout') {
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'shahaneh_panel_logout')) {
                status_header(403); exit('درخواست نامعتبر است.');
            }
            $this->clear_session_cookie();
            wp_safe_redirect(home_url('/panel'));
            exit;
        }
        if ($action !== 'login') { status_header(400); exit('درخواست نامعتبر است.'); }
        $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field(wp_unslash($_POST['_wpnonce'])) : '';
        if (!$nonce || !wp_verify_nonce($nonce, 'shahaneh_panel_login')) { status_header(403); $this->render('نشست فرم منقضی شده است. دوباره تلاش کنید.'); exit; }
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
        $rate_key = 'shp_login_' . hash_hmac('sha256', $ip, wp_salt('auth'));
        $attempts = (int) get_transient($rate_key);
        if ($attempts >= 6) { status_header(429); $this->render('تعداد تلاش‌ها زیاد است. ۱۵ دقیقه دیگر دوباره امتحان کنید.'); exit; }
        $email = isset($_POST['panel_email']) ? strtolower(sanitize_email(wp_unslash($_POST['panel_email']))) : '';
        $password = isset($_POST['panel_password']) ? (string) wp_unslash($_POST['panel_password']) : '';
        $hash = (string) get_option(self::OPTION_HASH, '');
        $user = $email && is_email($email) ? get_user_by('email', $email) : false;
        $authorized = $user && $this->allowed_admin_id((int) $user->ID);
        if (!$authorized || !$hash || !password_verify($password, $hash)) {
            set_transient($rate_key, $attempts + 1, 15 * MINUTE_IN_SECONDS);
            status_header(401); $this->render('ایمیل یا رمز پنل صحیح نیست، یا حساب شما مجاز نیست.'); exit;
        }
        delete_transient($rate_key);
        $this->set_session_cookie((int) $user->ID);
        wp_safe_redirect(home_url('/panel'));
        exit;
    }
    private function allowed_admin_id(int $user_id): bool {
        $user = get_user_by('id', $user_id);
        if (!$user || !user_can($user, 'manage_options')) { return false; }
        $allowed = array_filter(array_map('trim', explode("\n", (string) get_option(self::OPTION_EMAILS, ''))));
        return $allowed && in_array(strtolower((string) $user->user_email), array_map('strtolower', $allowed), true);
    }
    private function session_payload(int $user_id): string {
        $expires = (string) (time() + self::SESSION_TTL);
        $user = (string) $user_id;
        $nonce = bin2hex(random_bytes(16));
        $data = $user . '|' . $expires . '|' . $nonce;
        $sig = hash_hmac('sha256', $data, wp_salt('auth'));
        return base64_encode($data . '|' . $sig);
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
    private function valid_session(): bool {
        return $this->session_user_id() > 0;
    }
    private function set_session_cookie(int $user_id): void {
        if (!$this->allowed_admin_id($user_id)) { return; }
        $secure = is_ssl();
        setcookie(self::COOKIE, $this->session_payload($user_id), [
            'expires' => time() + self::SESSION_TTL, 'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN, 'secure' => $secure, 'httponly' => true, 'samesite' => 'Strict'
        ]);
    }
    private function clear_session_cookie(): void {
        setcookie(self::COOKIE, '', [
            'expires' => time() - HOUR_IN_SECONDS, 'path' => COOKIEPATH ?: '/',
            'domain' => COOKIE_DOMAIN, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Strict'
        ]);
        unset($_COOKIE[self::COOKIE]);
    }
    private function render(string $error = ''): void {
        $authenticated = $this->valid_session();
        $password_ready = (bool) get_option(self::OPTION_HASH, '');
        status_header(200);
        nocache_headers();
        ?><!doctype html><html lang="fa" dir="rtl"><head><meta charset="<?php bloginfo('charset'); ?>"><meta name="viewport" content="width=device-width,initial-scale=1"><title>پنل شاهانه</title><link rel="stylesheet" href="<?php echo esc_url(SHAHANEH_PANEL_URL . 'assets/panel.css?ver=' . SHAHANEH_PANEL_VERSION); ?>"></head>
        <body class="shp-body"><div class="shp-shell"><aside class="shp-side"><div class="shp-brand"><span>ش</span><div><b>شاهانه</b><small>SHAHANEH PANEL</small></div></div><p>مدیریت فروشگاه</p><nav><a class="active" href="<?php echo esc_url(home_url('/panel')); ?>">نمای کلی</a><span>محصولات</span><span>سفارش‌ها</span><span>مشتریان</span><span>انبار و گزارش‌ها</span><span>تنظیمات</span></nav><small class="shp-side-foot">نسخه پایه · ۰.۱.۰</small></aside>
        <main class="shp-main"><header><div><small>پنل اختصاصی مدیریت</small><h1><?php echo $authenticated ? 'خوش آمدید به پنل شاهانه' : 'ورود امن به پنل شاهانه'; ?></h1></div><?php if ($authenticated): ?><form method="post"><input type="hidden" name="panel_action" value="logout"><?php wp_nonce_field('shahaneh_panel_logout'); ?><button class="shp-logout">خروج امن</button></form><?php endif; ?></header>
        <?php if (!$password_ready): ?><section class="shp-card"><h2>راه‌اندازی اولیه</h2><p>برای تعیین رمز مستقل، ابتدا با حساب مدیر وردپرس وارد شوید و از مسیر «تنظیمات ← پنل شاهانه» رمز پنل را تعیین کنید.</p><a href="<?php echo esc_url(admin_url('options-general.php?page=shahaneh-panel')); ?>">رفتن به تنظیمات پنل</a></section>
        <?php elseif (!$authenticated): ?><section class="shp-login"><div class="shp-emblem">ش</div><h2>ورود مدیر</h2><p>برای ورود، ایمیل مدیرِ مجاز و رمز مستقل پنل لازم است. ورود به پیشخوان وردپرس جداگانه انجام نمی‌شود.</p><?php if ($error): ?><div class="shp-error"><?php echo esc_html($error); ?></div><?php endif; ?><form method="post"><input type="hidden" name="panel_action" value="login"><?php wp_nonce_field('shahaneh_panel_login'); ?><label for="panel_email">ایمیل مدیر مجاز</label><input id="panel_email" name="panel_email" type="email" autocomplete="username" required><label for="panel_password">رمز مستقل پنل</label><input id="panel_password" name="panel_password" type="password" autocomplete="current-password" required><button type="submit">ورود به پنل</button></form></section>
        <?php else: $panel_user = get_user_by('id', $this->session_user_id()); ?><section class="shp-welcome"><span class="shp-check">✓</span><div><h2>خوش آمدید<?php echo $panel_user ? '، ' . esc_html($panel_user->display_name) : ''; ?> به پنل شاهانه</h2><p>احراز هویت مستقل پنل انجام شد. ماژول‌های اطلاعات زنده ووکامرس در فازهای بعدی اضافه می‌شوند.</p></div></section><div class="shp-grid"><article class="shp-card"><small>وضعیت اتصال</small><h3>در انتظار پیکربندی</h3><p>هیچ داده نمایشی به‌عنوان داده واقعی نمایش داده نمی‌شود.</p></article><article class="shp-card"><small>امنیت نشست</small><h3>فعال</h3><p>کوکی امضاشده، HttpOnly و محدود به مدیر مجاز.</p></article><article class="shp-card"><small>گام بعدی</small><h3>داشبورد زنده</h3><p>اتصال امن WooCommerce در فاز دوم.</p></article></div><?php endif; ?>
        <footer>شاهانه · پنل مدیریت اختصاصی · <?php echo esc_html(wp_date('Y')); ?></footer></main></div></body></html><?php
    }
}
