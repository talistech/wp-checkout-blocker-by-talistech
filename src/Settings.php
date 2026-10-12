<?php
/**
 * Settings integration for Checkout Blocker.
 *
 * @package TalistechCheckoutBlocker
 */

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

/** Validate and retain site-specific checkout rules. */
final class Settings {

	public const OPTION_KEY   = 'talistech_checkout_blocker_settings';
	public const OPTION_GROUP = 'talistech_checkout_blocker';
	public const PAGE_SLUG    = 'talistech-checkout-blocker';

	/**
	 * Return safe settings defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'email_rules'     => '',
			'name_rules'      => '',
			'address_rules'   => '',
			'notice_text'     => __( 'Checkout is not available for this account. Please contact support.', 'talistech-checkout-blocker-for-woocommerce' ),
			'notify_admins'   => 0,
			'force_logout'    => 0,
			'logout_redirect' => home_url( '/' ),
		);
	}

	public static function activate( bool $networkWide = false ): void {
		if ( is_multisite() && $networkWide ) {
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
						self::activate();
					} finally {
						restore_current_blog();
					}
				}
				$siteCount = count( $sites );
				$offset   += $siteCount;
			} while ( $siteCount === 100 );
			return;
		}
		if ( get_option( self::OPTION_KEY, null ) === null ) {
			add_option( self::OPTION_KEY, self::defaults(), '', false );
		}
	}

	public static function initializeSite( \WP_Site $site ): void {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( plugin_basename( TCB_PLUGIN_FILE ) ) ) {
			return;
		}
		switch_to_blog( (int) $site->blog_id );
		try {
			self::activate();
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Read settings, replacing unexpected types with safe defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$settings = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$defaults = self::defaults();
		foreach ( $defaults as $key => $default ) {
			if ( is_string( $default ) ) {
				$defaults[ $key ] = is_string( $settings[ $key ] ?? null ) ? $settings[ $key ] : $default;
			} else {
				$defaults[ $key ] = self::checkbox( $settings[ $key ] ?? 0 );
			}
		}
		return $defaults;
	}

	public static function register(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Validate submitted settings and report invalid rules.
	 *
	 * @param mixed $input
	 * @return array<string, mixed>
	 */
	public static function sanitize( mixed $input ): array {
		$input  = is_array( $input ) ? $input : array();
		$output = self::defaults();

		foreach ( array( 'email_rules', 'name_rules', 'address_rules' ) as $key ) {
			[$clean, $invalid] = self::sanitizeLines( self::text( $input[ $key ] ?? '' ), $key );
			$output[ $key ]    = $clean;

			if ( $invalid !== array() ) {
				$label = match ( $key ) {
					'email_rules' => __( 'email', 'talistech-checkout-blocker-for-woocommerce' ),
					'name_rules' => __( 'name', 'talistech-checkout-blocker-for-woocommerce' ),
					default => __( 'address', 'talistech-checkout-blocker-for-woocommerce' ),
				};
				add_settings_error(
					self::OPTION_KEY,
					'invalid_' . $key,
					esc_html(
						sprintf(
							/* translators: 1: rule type, 2: invalid values */
							__( 'Some invalid %1$s rules were skipped: %2$s', 'talistech-checkout-blocker-for-woocommerce' ),
							$label,
							implode( ', ', array_slice( $invalid, 0, 5 ) )
						)
					),
					'warning'
				);
			}
		}

		$notice                    = sanitize_text_field( self::text( $input['notice_text'] ?? '' ) );
		$output['notice_text']     = $notice !== '' ? $notice : self::defaults()['notice_text'];
		$output['notify_admins']   = self::checkbox( $input['notify_admins'] ?? 0 );
		$output['force_logout']    = self::checkbox( $input['force_logout'] ?? 0 );
		$output['logout_redirect'] = self::localRedirect( $input['logout_redirect'] ?? '' );

		return $output;
	}

	public static function localRedirect( mixed $value ): string {
		$fallback = home_url( '/' );
		$redirect = esc_url_raw( self::text( $value ) );
		$host     = wp_parse_url( $redirect, PHP_URL_HOST );
		$scheme   = wp_parse_url( $redirect, PHP_URL_SCHEME );
		if ( $redirect === '' || $host === null || ! in_array( $scheme, array( 'http', 'https' ), true )
			|| strtolower( (string) $host ) !== strtolower( (string) wp_parse_url( $fallback, PHP_URL_HOST ) )
			|| wp_parse_url( $redirect, PHP_URL_USER ) !== null || wp_parse_url( $redirect, PHP_URL_PASS ) !== null ) {
			return $fallback;
		}
		return wp_validate_redirect( $redirect, $fallback );
	}

	private static function text( mixed $value ): string {
		return is_scalar( $value ) ? (string) $value : '';
	}

	private static function checkbox( mixed $value ): int {
		return in_array( $value, array( 1, '1', true ), true ) ? 1 : 0;
	}

	public static function normalizeRule( string $value, string $type ): ?string {
		$value = trim( sanitize_text_field( $value ) );
		if ( $value === '' ) {
			return null;
		}

		if ( $type === 'email_rules' ) {
			$value = strtolower( $value );
			return RuleMatcher::isValidEmailRule( $value ) ? $value : null;
		}

		if ( $type === 'name_rules' ) {
			$value = RuleMatcher::normalizeText( $value );
			return $value !== '' ? $value : null;
		}

		if ( $type === 'address_rules' ) {
			return RuleMatcher::normalizeAddressRule( $value );
		}

		return null;
	}

	/**
	 * Normalize and deduplicate rules for a category.
	 *
	 * @return array{0: string, 1: list<string>}
	 */
	private static function sanitizeLines( string $raw, string $type ): array {
		$lines   = preg_split( '/\R+/u', $raw ) ?: array();
		$valid   = array();
		$invalid = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( $line === '' ) {
				continue;
			}

			$normalized = self::normalizeRule( $line, $type );
			if ( $normalized === null ) {
				if ( $type !== 'name_rules' ) {
					$invalid[] = $type === 'email_rules' ? strtolower( $line ) : $line;
				}
				continue;
			}

			$valid[] = $normalized;
		}

		return array( implode( "\n", array_values( array_unique( $valid ) ) ), array_values( array_unique( $invalid ) ) );
	}
}
