<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Read-only WooCommerce dashboard for the authenticated Shahaneh panel.
 * All values are queried from the current WordPress/WooCommerce installation.
 */
final class Shahaneh_Dashboard {
    public static function render(int $user_id): void {
        $user = get_user_by('id', $user_id);
        echo '<section class="shp-welcome"><span class="shp-check">✓</span><div><h2>خوش آمدید' . ($user ? '، ' . esc_html($user->display_name) : '') . ' به پنل شاهانه</h2><p>نمای کلی فروشگاه بر اساس اطلاعات ثبت‌شده در همین سایت آزمایشی</p></div></section>';

        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            echo '<section class="shp-card shp-notice"><h2>ووکامرس در دسترس نیست</h2><p>برای نمایش آمار واقعی، افزونه WooCommerce باید روی این نصب فعال باشد.</p></section>';
            return;
        }

        $today_start = wp_date('Y-m-d 00:00:00', current_time('timestamp'));
        $month_start = wp_date('Y-m-01 00:00:00', current_time('timestamp'));
        $sales_statuses = ['processing', 'completed'];
        $today_orders = wc_get_orders(['status' => $sales_statuses, 'date_created' => '>=' . $today_start, 'limit' => -1, 'return' => 'objects']);
        $month_orders = wc_get_orders(['status' => $sales_statuses, 'date_created' => '>=' . $month_start, 'limit' => -1, 'return' => 'objects']);
        $recent_orders = wc_get_orders(['limit' => 8, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects']);
        $today_revenue = self::sum_orders($today_orders);
        $month_revenue = self::sum_orders($month_orders);

        $order_counts = wp_count_posts('shop_order');
        $pending = isset($order_counts->wc_pending) ? (int) $order_counts->wc_pending : 0;
        $processing = isset($order_counts->wc_processing) ? (int) $order_counts->wc_processing : 0;
        $completed = isset($order_counts->wc_completed) ? (int) $order_counts->wc_completed : 0;
        $product_counts = wp_count_posts('product');
        $product_total = $product_counts ? (int) $product_counts->publish : 0;
        $low_stock = self::low_stock_products();

        echo '<div class="shp-section-head"><div><h2>نمای کلی فروشگاه</h2><p>آمار به‌روزشده از ووکامرس · زمان محلی سایت</p></div><span class="shp-live"><i></i> اتصال فعال ووکامرس</span></div>';
        echo '<div class="shp-metrics">';
        self::metric('فروش امروز', self::money($today_revenue), 'مجموع سفارش‌های درحال انجام و تکمیل‌شده');
        self::metric('فروش این ماه', self::money($month_revenue), 'از ابتدای ماه جاری');
        self::metric('سفارش‌های در انتظار', number_format_i18n($pending), 'نیازمند بررسی مدیر');
        self::metric('سفارش‌های درحال انجام', number_format_i18n($processing), 'در حال پردازش');
        self::metric('کل محصولات منتشرشده', number_format_i18n($product_total), 'محصولات قابل مشاهده در فروشگاه');
        self::metric('سفارش‌های تکمیل‌شده', number_format_i18n($completed), 'وضعیت تکمیل‌شده');
        echo '</div>';

        echo '<div class="shp-dashboard-columns"><section class="shp-card shp-table-card"><div class="shp-card-head"><div><h3>آخرین سفارش‌ها</h3><p>۸ سفارش اخیر ثبت‌شده</p></div><span class="shp-subtle">اطلاعات زنده</span></div>';
        if (empty($recent_orders)) {
            echo '<div class="shp-empty">هنوز سفارشی در فروشگاه ثبت نشده است.</div>';
        } else {
            echo '<div class="shp-table-wrap"><table class="shp-table"><thead><tr><th>سفارش</th><th>مشتری</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th></tr></thead><tbody>';
            foreach ($recent_orders as $order) {
                if (!$order instanceof WC_Order) { continue; }
                $name = trim($order->get_formatted_billing_full_name());
                if ($name === '') { $name = 'مشتری مهمان'; }
                $date = $order->get_date_created();
                echo '<tr><td>#' . esc_html($order->get_order_number()) . '</td><td>' . esc_html($name) . '</td><td>' . wp_kses_post(wc_price((float) $order->get_total(), ['currency' => $order->get_currency()])) . '</td><td><span class="shp-status shp-status-' . esc_attr($order->get_status()) . '">' . esc_html(wc_get_order_status_name($order->get_status())) . '</span></td><td>' . ($date ? esc_html(wp_date('Y/m/d', $date->getTimestamp())) : '—') . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }
        echo '</section><section class="shp-card shp-low-stock"><div class="shp-card-head"><div><h3>هشدار موجودی</h3><p>محصولات نزدیک به اتمام</p></div><span class="shp-warning-count">' . number_format_i18n(count($low_stock)) . '</span></div>';
        if (empty($low_stock)) {
            echo '<div class="shp-empty">در حال حاضر محصولی زیر حد هشدار موجودی پیدا نشد.</div>';
        } else {
            echo '<ul class="shp-stock-list">';
            foreach ($low_stock as $product) {
                echo '<li><span>' . esc_html($product->get_name()) . '</span><b>' . esc_html((string) $product->get_stock_quantity()) . ' عدد</b></li>';
            }
            echo '</ul>';
        }
        echo '</section></div>';
        echo '<p class="shp-data-note">مبالغ از مجموع مبلغ سفارش‌ها محاسبه شده‌اند و سود خالص نیستند؛ هزینه خرید، ارسال و کارمزدها در این عدد کسر نشده‌اند.</p>';
    }

    private static function sum_orders(array $orders): float {
        $sum = 0.0;
        foreach ($orders as $order) {
            if ($order instanceof WC_Order) { $sum += (float) $order->get_total(); }
        }
        return $sum;
    }

    private static function money(float $amount): string {
        return wp_strip_all_tags(wc_price($amount));
    }

    private static function metric(string $label, string $value, string $note): void {
        echo '<article class="shp-metric"><span>' . esc_html($label) . '</span><strong>' . esc_html($value) . '</strong><small>' . esc_html($note) . '</small></article>';
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
