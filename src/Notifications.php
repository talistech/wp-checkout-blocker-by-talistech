<?php
/**
 * Notifications integration for Checkout Blocker.
 *
 * @package TalistechCheckoutBlocker
 */

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

/** Send optional administrator mail with shared notification limits. */
final class Notifications {

	public const STATE_KEY = 'talistech_checkout_blocker_notifications';
	public const LOCK_KEY  = 'talistech_checkout_blocker_notification_lock';

	/**
	 * Whether this request has already reserved a notification.
	 *
	 * @var bool
	 */
	private bool $sent = false;

	/**
	 * Send a notification after reserving the shared rate limit.
	 *
	 * @param array{type: string, rule: string, value: string} $match
	 * @param array<string, string> $context
	 */
	// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames -- Preserve the public named argument from existing releases.
	public function maybeSend( array $match, array $context ): void {
		if ( $this->sent || empty( Settings::get()['notify_admins'] ) ) {
			return;
		}

		$email = $context['email'] ?? '';
		try {
			$recipients = $this->recipients( $context );
			if ( $recipients === array() || ! $this->reserveNotification( $context['ip'] ?? '' ) ) {
				return;
			}
		} catch ( \Throwable $error ) {
			$this->mailFailure();
			return;
		}
		$this->sent = true;

		$label = match ( $match['type'] ) {
			RuleMatcher::EMAIL => __( 'email', 'checkout-blocker-by-talistech' ),
			RuleMatcher::NAME => __( 'name', 'checkout-blocker-by-talistech' ),
			default => __( 'address', 'checkout-blocker-by-talistech' ),
		};
		$who     = $email !== '' ? $email : trim( ( $context['first_name'] ?? '' ) . ' ' . ( $context['last_name'] ?? '' ) );
		$who     = $who !== '' ? $who : __( 'unknown user', 'checkout-blocker-by-talistech' );
		$subject = sprintf(
			/* translators: 1: matching rule type, 2: customer email or name. */
			__( 'Blocked checkout (%1$s): %2$s', 'checkout-blocker-by-talistech' ),
			$label,
			$who
		);

		$lines = array(
			__( 'A checkout was blocked for a user matching the blocklist.', 'checkout-blocker-by-talistech' ),
			'',
			sprintf(
				/* translators: 1: rule type, 2: matching rule */
				__( 'Reason: %1$s match (%2$s)', 'checkout-blocker-by-talistech' ),
				$match['type'],
				$match['rule']
			),
			self::detailLine(
				__( 'Name', 'checkout-blocker-by-talistech' ),
				trim( ( $context['first_name'] ?? '' ) . ' ' . ( $context['last_name'] ?? '' ) )
			),
			self::detailLine( __( 'Account email', 'checkout-blocker-by-talistech' ), $context['account_email'] ?? '' ),
			self::detailLine( __( 'Email', 'checkout-blocker-by-talistech' ), $context['email'] ?? '' ),
			self::detailLine( __( 'Address', 'checkout-blocker-by-talistech' ), $context['address_1'] ?? '' ),
			self::detailLine( __( 'Postcode', 'checkout-blocker-by-talistech' ), $context['postcode'] ?? '' ),
			self::detailLine( __( 'Country', 'checkout-blocker-by-talistech' ), $context['country'] ?? '' ),
			self::detailLine( __( 'IP', 'checkout-blocker-by-talistech' ), $context['ip'] ?? '' ),
			self::detailLine( __( 'User-Agent', 'checkout-blocker-by-talistech' ), $context['user_agent'] ?? '' ),
			self::detailLine( __( 'When', 'checkout-blocker-by-talistech' ), current_time( 'mysql' ) ),
			self::detailLine( __( 'URL', 'checkout-blocker-by-talistech' ), $context['url'] ?? home_url( '/' ) ),
			self::detailLine( __( 'Site', 'checkout-blocker-by-talistech' ), home_url( '/' ) ),
		);

		try {
			if ( ! wp_mail( $recipients, $subject, implode( "\n", $lines ), array( 'Content-Type: text/plain; charset=UTF-8' ) ) ) {
				$this->mailFailure();
			}
		} catch ( \Throwable $error ) {
			$this->mailFailure();
		}
	}

	/**
	 * Resolve and validate administrator mail recipients.
	 *
	 * @param array<string, string> $context
	 * @return list<string>
	 */
	private function recipients( array $context ): array {
		$recipients = array();
		$admins     = get_users(
			array(
				'role'   => 'administrator',
				'fields' => array( 'user_email' ),
			)
		);
		foreach ( (array) $admins as $admin ) {
			if ( is_object( $admin ) && ! empty( $admin->user_email ) && is_email( $admin->user_email ) ) {
				$recipients[] = sanitize_email( $admin->user_email );
			}
		}

		$siteAdmin = get_option( 'admin_email' );
		if ( $recipients === array() && is_email( $siteAdmin ) ) {
			$recipients[] = sanitize_email( $siteAdmin );
		}

		/**
	 * Filter administrator notification recipients.
		 *
		 * @param list<string> $recipients
		 * @param array<string, string> $context
		 */
		$filtered = apply_filters( 'talistech_checkout_blocker_notification_recipients', $recipients, $context );
		$filtered = array_map( 'sanitize_email', array_filter( (array) $filtered, 'is_string' ) );

		return array_values(
			array_unique(
				array_filter(
					$filtered,
					static fn( string $email ): bool => is_email( $email ) !== false
				)
			)
		);
	}

	private function reserveNotification( string $ip ): bool {
		global $wpdb;
		$now = time();
		// Connection-owned MySQL/MariaDB locks serialize the site budget and release on disconnect.
		$lockName = 'tcb_notice_' . substr( hash( 'sha256', DB_NAME . '|' . $wpdb->options ), 0, 48 );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Prepared, connection-owned lock; not cacheable.
		$acquired = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lockName ) );
		if ( (string) $acquired !== '1' ) {
			return false;
		}
		try {
			// Other requests may have updated the budget while we waited for the lock.
			wp_cache_delete( self::STATE_KEY, 'options' );
			wp_cache_delete( 'notoptions', 'options' );
			$state   = get_option( self::STATE_KEY, array() );
			$state   = is_array( $state ) ? $state : array();
			$ips     = is_array( $state['ips'] ?? null ) ? $state['ips'] : array();
			$ips     = array_filter( $ips, static fn( $expiry ): bool => is_int( $expiry ) && $expiry > $now );
			$started = (int) ( $state['started'] ?? 0 );
			$count   = (int) ( $state['count'] ?? 0 );
			if ( $started + HOUR_IN_SECONDS <= $now ) {
				$started = $now;
				$count   = 0;
			}
			$key = hash_hmac( 'sha256', $ip !== '' ? $ip : 'unknown', wp_salt( 'auth' ) );
			if ( isset( $ips[ $key ] ) || $count >= 60 ) {
				update_option(
					self::STATE_KEY,
					array(
						'started' => $started,
						'count'   => $count,
						'ips'     => $ips,
					),
					false
				);
				return false;
			}
			$ips[ $key ] = $now + 10 * MINUTE_IN_SECONDS;
			return update_option(
				self::STATE_KEY,
				array(
					'started' => $started,
					'count'   => $count + 1,
					'ips'     => $ips,
				),
				false
			);
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Release only this connection's named lock.
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lockName ) );
		}
	}

	private function mailFailure(): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->warning( 'Checkout Blocker could not send an administrator notification.', array( 'source' => 'checkout-blocker-by-talistech' ) );
		}
	}

	private static function detailLine( string $label, string $value ): string {
		return $label . ': ' . $value;
	}
}
