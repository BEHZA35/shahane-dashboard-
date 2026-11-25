<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Shahaneh live WooCommerce dashboard. Read-only: no store data is modified here.
 */
final class Shahaneh_Dashboard {
    public static function render(int $user_id): void {
        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            echo '<section class="shp-card"><h2>ووکامرس در دسترس نیست</h2><p>برای نمایش اطلاعات واقعی، WooCommerce باید فعال باشد.</p></section>';
            return;
        }

        $now = current_datetime();
        $today_start = $now->format('Y-m-d 00:00:00');
        $week_start = $now->modify('-6 days')->setTime(0, 0, 0);
        $today_orders = wc_get_orders(['status' => ['processing','completed','on-hold'], 'date_created' => '>=' . $today_start, 'limit' => -1, 'return' => 'objects']);
        $week_orders = wc_get_orders(['status' => ['processing','completed'], 'date_created' => '>=' . $week_start->format('Y-m-d H:i:s'), 'limit' => -1, 'return' => 'objects']);
        $recent_orders = wc_get_orders(['limit' => 5, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects']);
        $today_sales = self::sum_orders($today_orders);
        $today_count = count($today_orders);
        $pending = self::status_count('pending');
        $processing = self::status_count('processing');
        $completed = self::status_count('completed');
        $cancelled = self::status_count('cancelled') + self::status_count('failed');
        $total_status = max(1, $pending + $processing + $completed + $cancelled);
        $product_counts = wp_count_posts('product');
        $active_products = $product_counts ? (int) $product_counts->publish : 0;
        $new_customers = self::new_customers_today($today_start);
        $top_products = wc_get_products(['status' => 'publish', 'limit' => 3, 'orderby' => 'meta_value_num', 'meta_key' => 'total_sales', 'order' => 'DESC', 'return' => 'objects']);
        $low_stock = self::low_stock_products();

        echo '<section class="shp-hero-banner"><div class="shp-hero-copy"><span class="shp-hero-kicker">SHAHANEH · YOUR STORE, YOUR KINGDOM</span><h2>به پنل مدیریت فروشگاه شاهانه خوش آمدید</h2><p>موفقیت شما افتخار ماست <span>♥</span></p></div><div class="shp-hero-orbit"><i></i><b></b><em></em></div><div class="shp-hero-dots"><span></span><span></span><span></span></div></section>';

        echo '<div class="shp-modern-section-title"><div><span>نمای کلی فروشگاه</span><h2>امروز در شاهانه</h2></div><span class="shp-live"><i></i> اتصال زنده ووکامرس</span></div>';
        echo '<section class="shp-modern-metrics">';
        self::modern_metric('فروش امروز', self::money($today_sales), 'تومان', 'sales', 'trend');
        self::modern_metric('سفارش‌های امروز', number_format_i18n($today_count), 'سفارش ثبت‌شده', 'orders', 'cart');
        self::modern_metric('مشتریان جدید', number_format_i18n($new_customers), 'عضویت امروز', 'customers', 'users');
        self::modern_metric('محصولات فعال', number_format_i18n($active_products), 'محصول منتشرشده', 'products', 'box');
        echo '</section>';

        $days = self::weekly_sales($week_orders, $now);
        self::render_chart($days);

        echo '<section class="shp-modern-grid">';
        self::render_top_products($top_products);
        self::render_status_card($pending, $processing, $completed, $cancelled, $total_status);
        echo '</section>';

        echo '<section class="shp-modern-lower">';
        self::render_alerts($pending, $low_stock);
        self::render_recent_orders($recent_orders);
        echo '<aside class="shp-store-promo"><div class="shp-promo-crown">♛</div><div><small>SHAHANEH ONLINE STORE</small><h3>فروشگاه آنلاین شاهانه</h3><p>همیشه یک قدم جلوتر از سلیقه مشتریان</p><a href="' . esc_url(home_url('/')) . '" target="_blank" rel="noopener">مشاهده فروشگاه <b>←</b></a></div></aside>';
        echo '</section>';
        echo '<p class="shp-modern-note">مبالغ فروش، مجموع مبلغ سفارش‌ها هستند و به معنی سود خالص نیستند. آمار از اطلاعات همین فروشگاه خوانده شده است.</p>';
    }

    private static function status_count(string $status): int {
        return function_exists('wc_orders_count') ? (int) wc_orders_count($status) : 0;
    }

    private static function sum_orders(array $orders): float {
        $sum = 0.0;
        foreach ($orders as $order) { if ($order instanceof WC_Order) { $sum += (float) $order->get_total(); } }
        return $sum;
    }

    private static function money(float $amount): string {
        return wp_strip_all_tags(wc_price($amount));
    }

    private static function new_customers_today(string $start): int {
        $query = new WP_User_Query([
            'role' => 'customer',
            'date_query' => [['after' => $start, 'inclusive' => true]],
            'fields' => 'ID',
            'number' => 1,
            'count_total' => true,
        ]);
        return (int) $query->get_total();
    }

    private static function weekly_sales(array $orders, DateTimeImmutable $now): array {
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->modify('-' . $i . ' days');
            $days[$day->format('Y-m-d')] = ['label' => wp_date('l', $day->getTimestamp()), 'short' => wp_date('D', $day->getTimestamp()), 'total' => 0.0];
        }
        foreach ($orders as $order) {
            if (!$order instanceof WC_Order || !$order->get_date_created()) { continue; }
            $key = $order->get_date_created()->date('Y-m-d');
            if (isset($days[$key])) { $days[$key]['total'] += (float) $order->get_total(); }
        }
        return array_values($days);
    }

    private static function render_chart(array $days): void {
        $max = max(array_map(static fn($day) => (float) $day['total'], $days));
        $points = [];
        $labels = [];
        foreach ($days as $i => $day) {
            $x = 30 + ($i * 103);
            $y = $max > 0 ? 168 - (int) round(($day['total'] / $max) * 132) : 158;
            $points[] = $x . ',' . $y;
            $labels[] = '<span>' . esc_html($day['short']) . '</span>';
        }
        $point_string = implode(' ', $points);
        $first = explode(',', $points[0]);
        $last = explode(',', $points[count($points) - 1]);
        $area = 'M ' . $first[0] . ' 180 L ' . str_replace(' ', ' L ', $point_string) . ' L ' . $last[0] . ' 180 Z';
        echo '<article class="shp-modern-card shp-sales-chart"><div class="shp-modern-card-head"><div><small>عملکرد فروشگاه</small><h3>نمودار فروش ۷ روز گذشته</h3></div><span class="shp-chart-period">۷ روز اخیر</span></div>';
        echo '<div class="shp-svg-chart"><div class="shp-chart-ylabels"><span>بیشترین</span><span>متوسط</span><span>۰</span></div><svg viewBox="0 0 690 205" role="img" aria-label="نمودار فروش هفت روز اخیر">';
        foreach ([38,82,126,170] as $y) { echo '<line x1="20" y1="' . $y . '" x2="675" y2="' . $y . '" stroke="#e8f0ed" stroke-width="1" stroke-dasharray="4 5"/>'; }
        echo '<path d="' . esc_attr($area) . '" fill="url(#shpArea)" opacity=".8"/><defs><linearGradient id="shpArea" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#12b9b1" stop-opacity=".30"/><stop offset="1" stop-color="#12b9b1" stop-opacity=".02"/></linearGradient></defs>';
        echo '<polyline points="' . esc_attr($point_string) . '" fill="none" stroke="#0aa8a8" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>';
        foreach ($points as $point) { [$x,$y] = array_map('intval', explode(',', $point)); echo '<circle cx="' . $x . '" cy="' . $y . '" r="6" fill="#fff" stroke="#0799a5" stroke-width="4"/>'; }
        echo '</svg></div><div class="shp-chart-xlabels">' . implode('', $labels) . '</div><div class="shp-chart-foot"><span><i></i> فروش ثبت‌شده</span><b>بر اساس سفارش‌های در حال انجام و تکمیل‌شده</b></div></article>';
    }

    private static function render_top_products(array $products): void {
        echo '<article class="shp-modern-card shp-top-products"><div class="shp-modern-card-head"><div><small>محبوب‌ترین کالاها</small><h3>محصولات پرفروش <span>♛</span></h3></div><span class="shp-card-arrow">←</span></div>';
        if (!$products) { echo '<div class="shp-modern-empty">هنوز اطلاعات فروش محصولی ثبت نشده است.</div>'; }
        else {
            foreach ($products as $index => $product) {
                if (!$product instanceof WC_Product) { continue; }
                $image = $product->get_image('woocommerce_thumbnail', ['class' => 'shp-product-thumb']);
                echo '<div class="shp-product-row"><span class="shp-product-rank rank-' . ($index + 1) . '">' . ($index + 1) . '</span><div class="shp-product-image">' . wp_kses_post($image) . '</div><div class="shp-product-info"><b>' . esc_html($product->get_name()) . '</b><small>' . number_format_i18n((int) $product->get_total_sales()) . ' فروش</small></div><strong>' . wp_kses_post(wc_price((float) $product->get_price())) . '</strong></div>';
            }
        }
        echo '</article>';
    }

    private static function render_status_card(int $pending, int $processing, int $completed, int $cancelled, int $total): void {
        $p1 = ($pending / $total) * 100;
        $p2 = $p1 + ($processing / $total) * 100;
        $p3 = $p2 + ($completed / $total) * 100;
        $gradient = 'conic-gradient(#0799a5 0 ' . $p1 . '%,#3bbd78 ' . $p1 . '% ' . $p2 . '%,#8a67d6 ' . $p2 . '% ' . $p3 . '%,#ff9a32 ' . $p3 . '% 100%)';
        echo '<article class="shp-modern-card shp-order-status"><div class="shp-modern-card-head"><div><small>نمای کلی سفارش‌ها</small><h3>وضعیت سفارش‌ها</h3></div><span class="shp-status-cart">▣</span></div>';
        echo '<div class="shp-donut-wrap"><div class="shp-donut" style="background:' . esc_attr($gradient) . '"><div><b>' . number_format_i18n($pending + $processing + $completed + $cancelled) . '</b><small>کل سفارش‌ها</small></div></div></div>';
        echo '<ul class="shp-status-legend"><li><i class="is-processing"></i><span>در حال پردازش</span><b>' . number_format_i18n($processing) . '</b></li><li><i class="is-completed"></i><span>ارسال / تکمیل‌شده</span><b>' . number_format_i18n($completed) . '</b></li><li><i class="is-pending"></i><span>در انتظار پرداخت</span><b>' . number_format_i18n($pending) . '</b></li><li><i class="is-cancelled"></i><span>لغو / ناموفق</span><b>' . number_format_i18n($cancelled) . '</b></li></ul></article>';
    }

    private static function render_alerts(int $pending, array $low_stock): void {
        echo '<article class="shp-modern-card shp-alert-card"><div class="shp-modern-card-head"><div><small>مرکز پیگیری</small><h3>اعلان‌های مهم <span>♟</span></h3></div><span class="shp-card-arrow">←</span></div>';
        if ($pending > 0) { echo '<div class="shp-alert-row"><i class="alert-order">▣</i><span><b>' . number_format_i18n($pending) . ' سفارش در انتظار بررسی</b><small>نیازمند پیگیری پرداخت</small></span></div>'; }
        if ($low_stock) { echo '<div class="shp-alert-row"><i class="alert-stock">!</i><span><b>' . number_format_i18n(count($low_stock)) . ' محصول کم‌موجودی</b><small>موجودی کالاها را بررسی کنید</small></span></div>'; }
        if (!$pending && !$low_stock) { echo '<div class="shp-modern-empty">همه‌چیز مرتب است؛ اعلان مهمی وجود ندارد.</div>'; }
        echo '<div class="shp-alert-foot">به‌روزرسانی بر اساس داده‌های فعلی فروشگاه</div></article>';
    }

    private static function render_recent_orders(array $orders): void {
        echo '<article class="shp-modern-card shp-recent-orders"><div class="shp-modern-card-head"><div><small>آخرین تراکنش‌ها</small><h3>آخرین سفارش‌ها <span>▤</span></h3></div><span class="shp-card-arrow">←</span></div>';
        if (!$orders) { echo '<div class="shp-modern-empty">هنوز سفارشی ثبت نشده است.</div>'; }
        else {
            echo '<div class="shp-modern-table-wrap"><table class="shp-modern-table"><thead><tr><th>شماره سفارش</th><th>مشتری</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr></thead><tbody>';
            foreach ($orders as $order) {
                if (!$order instanceof WC_Order) { continue; }
                $name = trim($order->get_formatted_billing_full_name()) ?: 'مشتری مهمان';
                $date = $order->get_date_created();
                $status = $order->get_status();
                echo '<tr><td><b>#' . esc_html($order->get_order_number()) . '</b></td><td>' . esc_html($name) . '</td><td>' . wp_kses_post(wc_price((float) $order->get_total(), ['currency' => $order->get_currency()])) . '</td><td><span class="shp-modern-status status-' . esc_attr($status) . '">' . esc_html(wc_get_order_status_name($status)) . '</span></td><td>' . ($date ? esc_html(wp_date('Y/m/d', $date->getTimestamp())) : '—') . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</article>';
    }

    private static function low_stock_products(): array {
        $products = wc_get_products(['status' => 'publish', 'stock_status' => 'instock', 'limit' => 100, 'orderby' => 'stock_quantity', 'order' => 'ASC', 'return' => 'objects']);
        $low = [];
        foreach ($products as $product) {
            if (!$product instanceof WC_Product || !$product->managing_stock()) { continue; }
            $threshold = $product->get_low_stock_amount();
            if ($threshold === '' || $threshold === null) { $threshold = get_option('woocommerce_notify_low_stock_amount', 2); }
            $quantity = $product->get_stock_quantity();
            if ($quantity !== null && $quantity <= (int) $threshold) { $low[] = $product; }
            if (count($low) >= 6) { break; }
        }
        return $low;
    }

    private static function modern_metric(string $label, string $value, string $unit, string $tone, string $icon): void {
        echo '<article class="shp-modern-metric metric-' . esc_attr($tone) . '"><div class="shp-modern-metric-icon">' . self::icon($icon) . '</div><div class="shp-modern-metric-copy"><small>' . esc_html($label) . '</small><strong>' . esc_html($value) . '</strong><span>' . esc_html($unit) . '</span></div><i class="shp-metric-orb"></i></article>';
    }

    private static function icon(string $name): string {
        $icons = [
            'trend' => '<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M5 36 17 24l8 7 17-20M30 11h12v12" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 41V31M19 41V27M29 41V24M39 41V17" stroke="currentColor" stroke-width="4" stroke-linecap="round"/></svg>',
            'cart' => '<svg viewBox="0 0 48 48" aria-hidden="true"><path d="M5 8h6l5 25h22l5-18H14M19 40a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM36 40a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'users' => '<svg viewBox="0 0 48 48" aria-hidden="true"><circle cx="18" cy="15" r="7" fill="none" stroke="currentColor" stroke-width="3.5"/><path d="M4 39c0-8 6-13 14-13s14 5 14 13M33 10a6 6 0 0 1 0 12M36 28c5 1 8 5 8 11" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"/></svg>',
            'box' => '<svg viewBox="0 0 48 48" aria-hidden="true"><path d="m24 4 19 10v20L24 44 5 34V14L24 4Z M5 14l19 11 19-11M24 25v19M15 9l19 11" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linejoin="round"/></svg>',
        ];
        return $icons[$name] ?? '';
    }
}
