<?php
/**
 * CLI integration for Checkout Blocker.
 *
 * @package TalistechCheckoutBlocker
 */

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

/** Manage checkout rules and customer sessions through WP-CLI. */
final class CLI {

	public static function register(): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'talistech-cb', self::class );
		}
	}

	/**
	 * ## OPTIONS
	 * [--type=<type>]
	 * : email, name or address.
	 */
	public function list( array $args, array $assocArgs ): void {
		$type     = $this->type( $assocArgs['type'] ?? 'email' );
		$settings = Settings::get();
		$key      = $type . '_rules';
		foreach ( preg_split( '/\R+/u', (string) ( $settings[ $key ] ?? '' ) ) ?: array() as $line ) {
			$line = trim( $line );
			if ( $line !== '' ) {
				\WP_CLI::line( $line );
			}
		}
	}

	/**
	 * ## OPTIONS
	 * <value>
	 * : Rule to add.
	 * --type=<type>
	 * : email, name or address.
	 */
	public function add( array $args, array $assocArgs ): void {
		$type = $this->type( $assocArgs['type'] ?? 'email' );
		$this->update( $type, (string) ( $args[0] ?? '' ), false );
	}

	/**
	 * ## OPTIONS
	 * <value>
	 * : Rule to remove.
	 * --type=<type>
	 * : email, name or address.
	 */
	public function remove( array $args, array $assocArgs ): void {
		$type = $this->type( $assocArgs['type'] ?? 'email' );
		$this->update( $type, (string) ( $args[0] ?? '' ), true );
	}

	public function destroy_sessions(): void {
		$count = ( new Sessions() )->destroyExactEmailSessions();
		\WP_CLI::success( sprintf( 'Destroyed sessions for %d user(s).', $count ) );
	}

	private function type( string $type ): string {
		if ( ! in_array( $type, array( 'email', 'name', 'address' ), true ) ) {
			\WP_CLI::error( 'Type must be email, name or address.' );
		}
		return $type;
	}

	private function update( string $type, string $value, bool $remove ): void {
		$value = trim( $value );
		if ( $value === '' ) {
			\WP_CLI::error( 'Rule cannot be empty.' );
		}

		$settings   = Settings::get();
		$key        = $type . '_rules';
		$normalized = Settings::normalizeRule( $value, $key );
		if ( $normalized === null ) {
			\WP_CLI::error( 'Rule is invalid for type ' . $type . '.' );
		}

		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/\R+/u', (string) $settings[ $key ] ) ?: array() ) ) );
		if ( $remove ) {
			$lines = array_values( array_filter( $lines, static fn( string $line ): bool => $line !== $normalized ) );
		} elseif ( ! in_array( $normalized, $lines, true ) ) {
			$lines[] = $normalized;
		}
		$settings[ $key ] = implode( "\n", $lines );
		update_option( Settings::OPTION_KEY, Settings::sanitize( $settings ) );
		\WP_CLI::success( ( $remove ? 'Removed: ' : 'Added: ' ) . $normalized );
	}
}
