<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Read-only WooCommerce dashboard for the authenticated Shahaneh panel.
 */
final class Shahaneh_Dashboard {
    public static function render(int $user_id): void {
        $user = get_user_by('id', $user_id);
        echo '<section class="shp-welcome"><div class="shp-welcome-mark">✦</div><div><span class="shp-eyebrow">داشبورد اختصاصی فروشگاه</span><h2>خوش آمدید' . ($user ? '، ' . esc_html($user->display_name) : '') . '</h2><p>همه چیز برای مدیریت بهتر شاهانه، یک‌جا در دسترس شماست.</p></div><div class="shp-welcome-orb" aria-hidden="true"></div></section>';

        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            echo '<section class="shp-card shp-notice"><h2>ووکامرس در دسترس نیست</h2><p>برای نمایش آمار واقعی، افزونه WooCommerce باید روی این نصب فعال باشد.</p></section>';
            return;
        }

        $local_now = current_datetime();
        $today_start = $local_now->format('Y-m-d 00:00:00');
        $month_start = $local_now->format('Y-m-01 00:00:00');
        $sales_statuses = ['processing', 'completed'];
        $today_orders = wc_get_orders(['status' => $sales_statuses, 'date_created' => '>=' . $today_start, 'limit' => -1, 'return' => 'objects']);
        $month_orders = wc_get_orders(['status' => $sales_statuses, 'date_created' => '>=' . $month_start, 'limit' => -1, 'return' => 'objects']);
        $recent_orders = wc_get_orders(['limit' => 8, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects']);
        $today_revenue = self::sum_orders($today_orders);
        $month_revenue = self::sum_orders($month_orders);
        $pending = function_exists('wc_orders_count') ? (int) wc_orders_count('pending') : 0;
        $processing = function_exists('wc_orders_count') ? (int) wc_orders_count('processing') : 0;
        $completed = function_exists('wc_orders_count') ? (int) wc_orders_count('completed') : 0;
        $product_counts = wp_count_posts('product');
        $product_total = $product_counts ? (int) $product_counts->publish : 0;
        $low_stock = self::low_stock_products();

        echo '<div class="shp-section-head"><div><span class="shp-eyebrow">گزارش لحظه‌ای</span><h2>نمای کلی فروشگاه</h2><p>اطلاعات مستقیماً از ووکامرس خوانده می‌شود.</p></div><span class="shp-live"><i></i> اتصال فعال ووکامرس</span></div>';
        echo '<div class="shp-metrics">';
        self::metric('فروش امروز', self::money($today_revenue), 'مجموع سفارش‌های درحال انجام و تکمیل‌شده', 'تومان', 'gold');
        self::metric('فروش این ماه', self::money($month_revenue), 'از ابتدای ماه جاری', 'روند', 'teal');
        self::metric('سفارش‌های در انتظار', number_format_i18n($pending), 'نیازمند بررسی مدیر', 'سفارش', 'orange');
        self::metric('سفارش‌های درحال انجام', number_format_i18n($processing), 'در حال پردازش', 'ارسال', 'blue');
        self::metric('کل محصولات منتشرشده', number_format_i18n($product_total), 'محصولات قابل مشاهده در فروشگاه', 'محصول', 'purple');
        self::metric('سفارش‌های تکمیل‌شده', number_format_i18n($completed), 'وضعیت تکمیل‌شده', 'تکمیل', 'green');
        echo '</div>';

        self::weekly_chart();
        echo '<div class="shp-dashboard-columns"><section class="shp-card shp-table-card"><div class="shp-card-head"><div><span class="shp-eyebrow">آخرین فعالیت‌ها</span><h3>آخرین سفارش‌ها</h3><p>۸ سفارش اخیر ثبت‌شده</p></div><span class="shp-subtle">اطلاعات زنده</span></div>';
        if (empty($recent_orders)) {
            echo '<div class="shp-empty">هنوز سفارشی در فروشگاه ثبت نشده است.</div>';
        } else {
            echo '<div class="shp-table-wrap"><table class="shp-table"><thead><tr><th>سفارش</th><th>مشتری</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr></thead><tbody>';
            foreach ($recent_orders as $order) {
                if (!$order instanceof WC_Order) { continue; }
                $name = trim($order->get_formatted_billing_full_name());
                if ($name === '') { $name = 'مشتری مهمان'; }
                $date = $order->get_date_created();
                echo '<tr><td><b class="shp-order-id">#' . esc_html($order->get_order_number()) . '</b></td><td>' . esc_html($name) . '</td><td class="shp-order-total">' . wp_kses_post(wc_price((float) $order->get_total(), ['currency' => $order->get_currency()])) . '</td><td><span class="shp-status shp-status-' . esc_attr($order->get_status()) . '">' . esc_html(wc_get_order_status_name($order->get_status())) . '</span></td><td>' . ($date ? esc_html(wp_date('Y/m/d', $date->getTimestamp())) : '—') . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section><section class="shp-card shp-low-stock"><div class="shp-card-head"><div><span class="shp-eyebrow">نیازمند توجه</span><h3>هشدار موجودی</h3><p>محصولات نزدیک به اتمام</p></div><span class="shp-warning-count">' . number_format_i18n(count($low_stock)) . '</span></div>';
        if (empty($low_stock)) {
            echo '<div class="shp-empty">در حال حاضر محصولی زیر حد هشدار موجودی پیدا نشد.</div>';
        } else {
            echo '<ul class="shp-stock-list">';
            foreach ($low_stock as $product) {
                echo '<li><span class="shp-stock-dot"></span><span class="shp-stock-name">' . esc_html($product->get_name()) . '</span><b>' . esc_html((string) $product->get_stock_quantity()) . ' عدد</b></li>';
            }
            echo '</ul>';
        }
        echo '</section></div>';
        echo '<p class="shp-data-note"><span>i</span> مبالغ نمایش‌داده‌شده مجموع مبلغ سفارش‌ها هستند، نه سود خالص؛ هزینه خرید، ارسال و کارمزدها از آن کسر نشده‌اند.</p>';
    }

    private static function weekly_chart(): void {
        $now = current_datetime();
        $start = $now->modify('-6 days')->setTime(0, 0, 0);
        $orders = wc_get_orders(['status' => ['processing', 'completed'], 'date_created' => '>=' . $start->format('Y-m-d H:i:s'), 'limit' => -1, 'return' => 'objects']);
        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = $now->modify('-' . $i . ' days');
            $days[$day->format('Y-m-d')] = ['label' => wp_date('D', $day->getTimestamp()), 'total' => 0.0];
        }
        foreach ($orders as $order) {
            if (!$order instanceof WC_Order || !$order->get_date_created()) { continue; }
            $key = $order->get_date_created()->date('Y-m-d');
            if (isset($days[$key])) { $days[$key]['total'] += (float) $order->get_total(); }
        }
        $max = max(array_map(static fn($day) => $day['total'], $days));
        echo '<section class="shp-card shp-chart-card"><div class="shp-card-head"><div><span class="shp-eyebrow">عملکرد فروش</span><h3>روند فروش هفت روز اخیر</h3><p>بر اساس سفارش‌های درحال انجام و تکمیل‌شده</p></div><span class="shp-chart-tag">۷ روز گذشته</span></div><div class="shp-chart">';
        foreach ($days as $day) {
            $height = $max > 0 ? max(5, (int) round(($day['total'] / $max) * 100)) : 5;
            echo '<div class="shp-chart-day"><span class="shp-chart-value">' . esc_html(wp_strip_all_tags(wc_price($day['total']))) . '</span><div class="shp-chart-track"><i style="height:' . esc_attr((string) $height) . '%"></i></div><small>' . esc_html($day['label']) . '</small></div>';
        }
        echo '</div></section>';
    }

    private static function sum_orders(array $orders): float {
        $sum = 0.0;
        foreach ($orders as $order) { if ($order instanceof WC_Order) { $sum += (float) $order->get_total(); } }
        return $sum;
    }

    private static function money(float $amount): string { return wp_strip_all_tags(wc_price($amount)); }

    private static function metric(string $label, string $value, string $note, string $symbol, string $tone): void {
        echo '<article class="shp-metric shp-metric-' . esc_attr($tone) . '"><div class="shp-metric-top"><span>' . esc_html($label) . '</span><i>' . esc_html($symbol) . '</i></div><strong>' . esc_html($value) . '</strong><small>' . esc_html($note) . '</small><span class="shp-metric-glow" aria-hidden="true"></span></article>';
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
}
