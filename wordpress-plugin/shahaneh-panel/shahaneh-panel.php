<?php
/**
 * Plugin Name: Shahaneh Admin Panel
 * Description: Secure, RTL administration panel foundation for the Shahaneh WooCommerce store.
 * Version: 0.2.2
 * Requires at least: 6.2
 * Requires PHP: 8.0
 * Author: Shahaneh
 * Text Domain: shahaneh-panel
 */
if (!defined('ABSPATH')) { exit; }
define('SHAHANEH_PANEL_VERSION', '0.2.2');
define('SHAHANEH_PANEL_FILE', __FILE__);
define('SHAHANEH_PANEL_DIR', plugin_dir_path(__FILE__));
define('SHAHANEH_PANEL_URL', plugin_dir_url(__FILE__));
require_once SHAHANEH_PANEL_DIR . 'includes/class-shahaneh-panel.php';
require_once SHAHANEH_PANEL_DIR . 'includes/class-shahaneh-dashboard.php';
register_activation_hook(__FILE__, ['Shahaneh_Panel', 'activate']);
register_deactivation_hook(__FILE__, ['Shahaneh_Panel', 'deactivate']);
add_action('plugins_loaded', static function () {
    Shahaneh_Panel::instance()->init();
});
