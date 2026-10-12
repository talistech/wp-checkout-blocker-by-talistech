<?php
/**
 * Plugin Name: Talistech Checkout Blocker for WooCommerce
 * Description: Block WooCommerce checkout by email, name or billing address, with full support for shortcode and block-based checkout.
 * Version: 1.2.1
 * Author: Talistech
 * Author URI: https://www.talistech.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 10.9
 * WC tested up to: 11.2.1
 * Text Domain: talistech-checkout-blocker-for-woocommerce
 * Domain Path: /languages
 *
 * @package TalistechCheckoutBlocker
 */

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Older directory names share the same PHP API and settings. Do not load two copies.
$legacyActive = array_intersect(
	array(
		'checkout-blocker-by-talistech/checkout-blocker-by-talistech.php',
		'wp-checkout-blocker-by-talistech/wp-checkout-blocker-by-talistech.php',
	),
	array_merge(
		(array) get_option( 'active_plugins', array() ),
		is_multisite() ? array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) : array()
	)
);
if ( $legacyActive || defined( __NAMESPACE__ . '\\TCB_PLUGIN_FILE' ) ) {
	register_activation_hook(
		__FILE__,
		static function (): void {
			wp_die( esc_html__( 'Deactivate the older Talistech Checkout Blocker installation before activating this version. Your settings are retained.', 'talistech-checkout-blocker-for-woocommerce' ) );
		}
	);
	$duplicateNotice = static function (): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) ) {
			return;
		}
		$capability = is_network_admin() ? 'manage_network_plugins' : 'activate_plugins';
		if ( ! current_user_can( $capability ) ) {
			return;
		}
		echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__(
			'Another Talistech Checkout Blocker installation is already loaded. Deactivate the older installation before using this one. Existing settings are retained.',
			'talistech-checkout-blocker-for-woocommerce'
		) . '</p></div>';
	};
	add_action( 'admin_notices', $duplicateNotice );
	add_action( 'network_admin_notices', $duplicateNotice );
	unset( $duplicateNotice, $legacyActive );
	return;
}
unset( $legacyActive );

const TCB_PLUGIN_FILE = __FILE__;
const TCB_PLUGIN_DIR  = __DIR__;
const TCB_VERSION     = '1.2.1';

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
