<?php
/**
 * WooCommerce integration for Checkout Blocker.
 *
 * @package TalistechCheckoutBlocker
 */

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

use WP_Error;

/** Reject matching checkout submissions before payment. */
final class WooCommerce {

	public function __construct(
		private readonly RuleMatcher $matcher,
		private readonly Notifications $notifications
	) {
	}

	public function register(): void {
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validateClassicCheckout' ), 10, 2 );
		add_action( 'woocommerce_checkout_validate_order_before_payment', array( $this, 'validateBlocksCheckout' ), 10, 2 );
		add_action( 'woocommerce_before_pay_action', array( $this, 'validatePayOrder' ), 10 );
		add_action( 'template_redirect', array( $this, 'maybeForceLogout' ), 1 );
	}

	/**
	 * Add a blocking error to classic checkout validation.
	 *
	 * @param array<string, mixed> $data
	 */
	public function validateClassicCheckout( array $data, WP_Error $errors ): void {
		$context = $this->contextFromArray( $data );
		$match   = $this->matcher->match( $context );
		if ( $match === null ) {
			return;
		}

		$errors->add( 'talistech_checkout_blocked', $this->notice() );
		$this->notifications->maybeSend( $match, $context );
	}

	/** Validate an existing order after WooCommerce verifies its payment nonce and key. */
	public function validatePayOrder( object $order ): void {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}
		$context = $this->contextFromOrder( $order, true );
		$match   = $this->matcher->match( $context );
		if ( $match === null ) {
			return;
		}

		// The core payment handler refuses gateway processing when an error notice exists.
		wc_add_notice( $this->notice(), 'error' );
		$this->notifications->maybeSend( $match, $context );
	}

	public function validateBlocksCheckout( object $order, WP_Error $errors ): void {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$context = $this->contextFromOrder( $order );
		$match   = $this->matcher->match( $context );
		if ( $match === null ) {
			return;
		}

		$errors->add( 'talistech_checkout_blocked', $this->notice() );
		$this->notifications->maybeSend( $match, $context );
	}

	public function maybeForceLogout(): void {
		if ( empty( Settings::get()['force_logout'] ) || ! is_user_logged_in() || is_admin() || wp_doing_ajax() ) {
			return;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}

		$user = wp_get_current_user();
		if ( ! $user instanceof \WP_User || Sessions::isProtected( $user ) ) {
			return;
		}
		$email = $user instanceof \WP_User ? (string) $user->user_email : '';
		if ( $email === '' || $this->matcher->match( array( 'email' => $email ) ) === null ) {
			return;
		}

		do_action( 'talistech_checkout_blocker_user_blocked', $user );
		wp_logout();
		wp_safe_redirect( Settings::localRedirect( Settings::get()['logout_redirect'] ?? '' ) );
		exit;
	}

	/**
	 * Build matching context from submitted billing details.
	 *
	 * @param array<string, mixed> $data
	 * @return array<string, string>
	 */
	private function contextFromArray( array $data ): array {
		$context = array(
			'email'      => $this->text( $data['billing_email'] ?? '' ),
			'first_name' => $this->text( $data['billing_first_name'] ?? '' ),
			'last_name'  => $this->text( $data['billing_last_name'] ?? '' ),
			'address_1'  => $this->text( $data['billing_address_1'] ?? '' ),
			'postcode'   => $this->text( $data['billing_postcode'] ?? '' ),
			'country'    => $this->text( $data['billing_country'] ?? '' ),
		);

		return $this->completeContext( $context );
	}

	/**
	 * Build matching context from an order populated by the Store API.
	 *
	 * @return array<string, string>
	 */
	private function contextFromOrder( object $order, bool $includeOrderAccount = false ): array {
		$context = array(
			'email'      => $this->text( $order->get_billing_email() ),
			'first_name' => $this->text( $order->get_billing_first_name() ),
			'last_name'  => $this->text( $order->get_billing_last_name() ),
			'address_1'  => $this->text( $order->get_billing_address_1() ),
			'postcode'   => $this->text( $order->get_billing_postcode() ),
			'country'    => $this->text( $order->get_billing_country() ),
		);

		if ( $includeOrderAccount ) {
			// Payment links can be used while logged out or signed in to a different account.
			$customerId                     = (int) $order->get_customer_id();
			$customer                       = $customerId > 0 ? get_user_by( 'id', $customerId ) : false;
			$context['order_account_email'] = $customer instanceof \WP_User ? $this->text( $customer->user_email ) : '';
		}
		return $this->completeContext( $context );
	}

	/**
	 * Add the authenticated account email and notification context.
	 *
	 * @param array<string, string> $context
	 * @return array<string, string>
	 */
	private function completeContext( array $context ): array {
		$context['account_email'] = '';
		if ( is_user_logged_in() ) {
			$user = wp_get_current_user();
			if ( $user instanceof \WP_User ) {
				$context['account_email'] = $this->text( $user->user_email );
			}
		}

		$ip        = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';
		$userAgent = isset( $_SERVER['HTTP_USER_AGENT'] )
			? substr( sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ), 0, 512 )
			: '';
		// Do not include query parameters, which can contain tokens or other private data.
		$requestUri = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( esc_url_raw( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) ), PHP_URL_PATH ) : '/';
		$homeParts  = wp_parse_url( home_url( '/' ) );
		$origin     = $homeParts['scheme'] . '://' . $homeParts['host'];
		if ( isset( $homeParts['port'] ) ) {
			$origin .= ':' . $homeParts['port'];
		}

		return array_merge(
			$context,
			array(
				'ip'         => $ip,
				'user_agent' => $userAgent,
				'url'        => $origin . ( is_string( $requestUri ) ? $requestUri : '/' ),
			)
		);
	}

	private function text( mixed $value ): string {
		// WooCommerce has already unslashed submitted values; order getters are also unslashed.
		return is_scalar( $value ) ? sanitize_text_field( (string) $value ) : '';
	}

	private function notice(): string {
		$notice = (string) ( Settings::get()['notice_text'] ?? '' );
		return $notice !== '' ? $notice : __( 'Checkout is not available for this account. Please contact support.', 'checkout-blocker-by-talistech' );
	}
}
