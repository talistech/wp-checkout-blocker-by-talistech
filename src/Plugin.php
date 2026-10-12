<?php
/**
 * Plugin integration for Checkout Blocker.
 *
 * @package TalistechCheckoutBlocker
 */

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

/** Register WooCommerce integration and privacy guidance. */
final class Plugin {

	public static function boot(): void {
		add_action( 'admin_init', array( self::class, 'privacyPolicy' ) );
		if ( ! defined( 'WC_VERSION' ) || version_compare( (string) WC_VERSION, '10.9', '<' ) ) {
			add_action( 'admin_notices', array( self::class, 'dependencyNotice' ) );
			return;
		}

		if ( class_exists( 'Talistech_Checkout_Blocker' ) ) {
			add_action( 'admin_notices', array( self::class, 'legacyConflictNotice' ) );
			return;
		}

		$settings = Settings::get();
		$matcher  = new RuleMatcher( $settings );
		$sessions = new Sessions();
		$admin    = new Admin( $sessions );

		$admin->register();
		( new WooCommerce( $matcher, new Notifications() ) )->register();
		CLI::register();
	}

	public static function privacyPolicy(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		wp_add_privacy_policy_content(
			'Checkout Blocker by Talistech',
			wp_kses_post( '<p>' . __( 'This shop uses checkout block rules containing email addresses, names or billing addresses. For signed-in customers, both account and billing email addresses are checked. Rules are kept until an administrator removes them or uninstalls the plugin.', 'checkout-blocker-by-talistech' ) . '</p><p>' . __( 'If administrator notifications are enabled, matching checkout details (including account and billing email addresses, name, address, IP address, user agent, time and checkout URL without query parameters) are sent through this site\'s WordPress mail system. Mail retention is controlled by the shop and its mail provider. The plugin does not transmit data to Talistech or use an external tracking service.', 'checkout-blocker-by-talistech' ) . '</p><p>' . __( 'Notification limits store keyed IP hashes with a ten-minute validity and an hourly counter. Expired hashes are pruned on the next notification attempt, and all limiter data is removed when the plugin is uninstalled. Adapt this text to the shop\'s actual purpose, retention periods and privacy practices.', 'checkout-blocker-by-talistech' ) . '</p>' )
		);
	}

	public static function dependencyNotice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p>' . esc_html__(
			'Checkout Blocker by Talistech requires WooCommerce 10.9 or newer and is currently inactive.',
			'checkout-blocker-by-talistech'
		) . '</p></div>';
	}

	public static function legacyConflictNotice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>' . esc_html__(
			'The legacy Talistech BV Checkout Blocker is active. Deactivate it before using this plugin to avoid duplicate checkout validation.',
			'checkout-blocker-by-talistech'
		) . '</p></div>';
	}
}
