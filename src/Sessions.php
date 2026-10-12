<?php
/**
 * Sessions integration for Checkout Blocker.
 *
 * @package TalistechCheckoutBlocker
 */

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

/** Invalidate customer sessions while protecting management accounts. */
final class Sessions {

	public function destroyExactEmailSessions(): int {
		$settings = Settings::get();
		$matcher  = new RuleMatcher( $settings );
		$count    = 0;
		$seen     = array();

		if ( ! class_exists( 'WP_Session_Tokens' ) ) {
			return 0;
		}

		foreach ( $matcher->emailRules() as $rule ) {
			if ( str_contains( $rule, '*' ) || ! filter_var( $rule, FILTER_VALIDATE_EMAIL ) ) {
				continue;
			}

			$user = get_user_by( 'email', $rule );
			if ( ! $user || isset( $seen[ $user->ID ] ) || self::isProtected( $user ) ) {
				continue;
			}
			$seen[ $user->ID ] = true;

			$tokens = \WP_Session_Tokens::get_instance( (int) $user->ID );
			if ( $tokens ) {
				$tokens->destroy_all();
				++$count;
			}
		}

		return $count;
	}

	public static function isProtected( \WP_User $user ): bool {
		if ( user_can( $user, 'manage_options' ) || user_can( $user, 'manage_woocommerce' ) ) {
			return true;
		}
		if ( is_multisite() ) {
			// WordPress sessions are shared across a network; protect managers of any member site.
			foreach ( get_blogs_of_user( $user->ID, true ) as $site ) {
				if ( user_can_for_site( $user->ID, $site->userblog_id, 'manage_options' )
					|| user_can_for_site( $user->ID, $site->userblog_id, 'manage_woocommerce' ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
