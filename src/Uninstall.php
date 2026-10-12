<?php
/**
 * Remove plugin-owned data.
 *
 * @package TalistechCheckoutBlocker
 */

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

/** Removes only this plugin's data, including legacy notification transients. */
final class Uninstall {

	public static function run(): void {
		if ( ! is_multisite() ) {
			self::cleanSite();
			return;
		}
		$offset = 0;
		do {
			$sites = get_sites(
				array(
					'fields' => 'ids',
					'number' => 100,
					'offset' => $offset,
				)
			);
			foreach ( $sites as $siteId ) {
				switch_to_blog( (int) $siteId );
				try {
					self::cleanSite();
				} finally {
					restore_current_blog();
				}
			}
			$siteCount = count( $sites );
			$offset   += $siteCount;
		} while ( $siteCount === 100 );
	}

	private static function cleanSite(): void {
		delete_option( 'talistech_checkout_blocker_settings' );
		delete_option( 'talistech_checkout_blocker_notifications' );
		delete_option( 'talistech_checkout_blocker_notification_lock' );
		global $wpdb;
		// Legacy transient keys are not enumerated anywhere, so remove only our exact prefixes.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall cleanup of plugin-owned options.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_tcb_notice_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_tcb_notice_' ) . '%'
			)
		);
		// Persistent object caches may still hold a negative/positive options lookup.
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}
}
