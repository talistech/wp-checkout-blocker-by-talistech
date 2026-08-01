<?php
/**
 * Plugin Name: Checkout Blocker by Talistech BV
 * Plugin URI: https://github.com/talistech/wp-checkout-blocker-by-talistech
 * Description: Block WooCommerce checkout by email, name or billing address, with full support for shortcode and block-based checkout.
 * Version: 1.1.0
 * Author: Talistech BV
 * Author URI: https://www.talistech.com
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 10.9
 * WC tested up to: 10.9
 * Text Domain: talistech-checkout-blocker
 * Domain Path: /languages
 * Update URI: https://github.com/talistech/wp-checkout-blocker-by-talistech
 */

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

if (! defined('ABSPATH')) {
    exit;
}

const TCB_PLUGIN_FILE = __FILE__;
const TCB_PLUGIN_DIR = __DIR__;
const TCB_VERSION = '1.1.0';

require_once TCB_PLUGIN_DIR . '/src/RuleMatcher.php';
require_once TCB_PLUGIN_DIR . '/src/Settings.php';
require_once TCB_PLUGIN_DIR . '/src/Notifications.php';
require_once TCB_PLUGIN_DIR . '/src/Sessions.php';
require_once TCB_PLUGIN_DIR . '/src/WooCommerce.php';
require_once TCB_PLUGIN_DIR . '/src/Admin.php';
require_once TCB_PLUGIN_DIR . '/src/CLI.php';
require_once TCB_PLUGIN_DIR . '/src/Plugin.php';

register_activation_hook(__FILE__, [Settings::class, 'activate']);

add_action('plugins_loaded', static function (): void {
    load_plugin_textdomain('talistech-checkout-blocker', false, dirname(plugin_basename(__FILE__)) . '/languages');
    Plugin::boot();
}, 20);

add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'cart_checkout_blocks',
            __FILE__,
            true
        );
    }
});
