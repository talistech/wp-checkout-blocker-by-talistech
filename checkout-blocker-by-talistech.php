<?php
/**
 * Plugin Name: Checkout Blocker by Talistech
 * Description: Block WooCommerce checkout by email, name or billing address, with full support for shortcode and block-based checkout.
 * Version: 1.2.0
 * Author: Talistech
 * Author URI: https://www.talistech.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 10.9
 * WC tested up to: 11.1.2
 * Text Domain: checkout-blocker-by-talistech
 * Domain Path: /languages
 *
 * @package TalistechCheckoutBlocker
 */

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const TCB_PLUGIN_FILE = __FILE__;
const TCB_PLUGIN_DIR  = __DIR__;
const TCB_VERSION     = '1.2.0';

require_once TCB_PLUGIN_DIR . '/src/RuleMatcher.php';
require_once TCB_PLUGIN_DIR . '/src/Settings.php';
require_once TCB_PLUGIN_DIR . '/src/Notifications.php';
require_once TCB_PLUGIN_DIR . '/src/Sessions.php';
require_once TCB_PLUGIN_DIR . '/src/WooCommerce.php';
require_once TCB_PLUGIN_DIR . '/src/Admin.php';
require_once TCB_PLUGIN_DIR . '/src/CLI.php';
require_once TCB_PLUGIN_DIR . '/src/Plugin.php';

register_activation_hook( __FILE__, array( Settings::class, 'activate' ) );
add_action( 'wp_initialize_site', array( Settings::class, 'initializeSite' ), 200 );

add_action(
	'init',
	static function (): void {
			Plugin::boot();
	},
	5
);

add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				__FILE__,
				true
			);
		}
	}
);
