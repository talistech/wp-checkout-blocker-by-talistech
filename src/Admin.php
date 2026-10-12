<?php
/**
 * Admin integration for Checkout Blocker.
 *
 * @package TalistechCheckoutBlocker
 */

declare(strict_types=1);

namespace Talistech\CheckoutBlocker;

/** Render settings and authorize administration actions. */
final class Admin {

	private const RULES_SECTION    = 'talistech_checkout_blocker_rules';
	private const BEHAVIOR_SECTION = 'talistech_checkout_blocker_behavior';

	public function __construct( private readonly Sessions $sessions ) {
	}

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'addMenu' ) );
		add_action( 'admin_init', array( Settings::class, 'register' ) );
		add_action( 'admin_init', array( self::class, 'registerFields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueueAssets' ) );
		add_action( 'admin_post_talistech_checkout_blocker_destroy_sessions', array( $this, 'destroySessions' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( TCB_PLUGIN_FILE ), array( $this, 'pluginLinks' ) );
	}

	public function addMenu(): void {
		add_options_page(
			__( 'Checkout Blocker', 'checkout-blocker-by-talistech' ),
			__( 'Checkout Blocker', 'checkout-blocker-by-talistech' ),
			'manage_options',
			Settings::PAGE_SLUG,
			array( $this, 'renderPage' )
		);
	}

	public function enqueueAssets( string $hookSuffix ): void {
		if ( $hookSuffix !== 'settings_page_' . Settings::PAGE_SLUG ) {
			return;
		}

		wp_enqueue_style(
			'talistech-checkout-blocker-admin',
			plugins_url( 'assets/admin.css', TCB_PLUGIN_FILE ),
			array(),
			TCB_VERSION
		);
	}

	/**
	 * Add a link to the plugin settings page.
	 *
	 * @param list<string> $links
	 * @return list<string>
	 */
	public function pluginLinks( array $links ): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}

		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'options-general.php?page=' . Settings::PAGE_SLUG ) ) . '">' .
			esc_html__( 'Settings', 'checkout-blocker-by-talistech' ) . '</a>'
		);
		return $links;
	}

	public function destroySessions(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'checkout-blocker-by-talistech' ) );
		}
		check_admin_referer( 'talistech_checkout_blocker_destroy_sessions' );

		$count = $this->sessions->destroyExactEmailSessions();
		$url   = add_query_arg(
			array(
				'page'               => Settings::PAGE_SLUG,
				'sessions_destroyed' => $count,
				'_tcb_result_nonce'  => wp_create_nonce( 'talistech_checkout_blocker_session_result' ),
			),
			admin_url( 'options-general.php' )
		);
		wp_safe_redirect( $url );
		exit;
	}

	public function renderPage(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$sessionsDestroyed = null;
		if ( isset( $_GET['sessions_destroyed'], $_GET['_tcb_result_nonce'] )
			&& is_string( $_GET['_tcb_result_nonce'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_tcb_result_nonce'] ) ), 'talistech_checkout_blocker_session_result' ) ) {
			$sessionsDestroyed = is_scalar( $_GET['sessions_destroyed'] ) ? absint( wp_unslash( (string) $_GET['sessions_destroyed'] ) ) : 0;
		}
		?>
		<div class="wrap tcb-admin">
			<header class="tcb-hero">
				<div class="tcb-hero__content">
					<span class="tcb-eyebrow"><?php esc_html_e( 'WooCommerce protection by Talistech', 'checkout-blocker-by-talistech' ); ?></span>
					<h1><?php esc_html_e( 'Checkout Blocker', 'checkout-blocker-by-talistech' ); ?></h1>
					<p><?php esc_html_e( 'Stop selected customers from placing an order using server-side email, name or billing address rules.', 'checkout-blocker-by-talistech' ); ?></p>
				</div>
				<div class="tcb-hero__meta" aria-label="<?php esc_attr_e( 'Compatibility', 'checkout-blocker-by-talistech' ); ?>">
					<span><?php esc_html_e( 'Server-side validation', 'checkout-blocker-by-talistech' ); ?></span>
					<span><?php esc_html_e( 'Classic + Checkout Blocks', 'checkout-blocker-by-talistech' ); ?></span>
				</div>
			</header>

			<?php settings_errors(); ?>
			<?php if ( $sessionsDestroyed !== null ) : ?>
				<div class="notice notice-success is-dismissible"><p>
					<?php
					printf(
						/* translators: %d: number of customer accounts whose sessions were destroyed. */
						esc_html__( 'Sessions destroyed for %d user(s).', 'checkout-blocker-by-talistech' ),
						esc_html( (string) $sessionsDestroyed )
					);
					?>
				</p></div>
			<?php endif; ?>

			<div class="tcb-layout">
				<main class="tcb-main">
					<form action="options.php" method="post" class="tcb-settings-form">
						<?php settings_fields( Settings::OPTION_GROUP ); ?>

						<section class="tcb-card" aria-labelledby="tcb-rules-title">
							<div class="tcb-card__header">
								<span class="tcb-step" aria-hidden="true">01</span>
								<div>
									<h2 id="tcb-rules-title"><?php esc_html_e( 'Block rules', 'checkout-blocker-by-talistech' ); ?></h2>
									<p><?php esc_html_e( 'Add one rule per line. A match in any one of the three lists blocks checkout.', 'checkout-blocker-by-talistech' ); ?></p>
								</div>
							</div>
							<table class="form-table tcb-form-table" role="presentation">
								<?php do_settings_fields( Settings::PAGE_SLUG, self::RULES_SECTION ); ?>
							</table>
						</section>

						<section class="tcb-card" aria-labelledby="tcb-behavior-title">
							<div class="tcb-card__header">
								<span class="tcb-step" aria-hidden="true">02</span>
								<div>
									<h2 id="tcb-behavior-title"><?php esc_html_e( 'Customer experience', 'checkout-blocker-by-talistech' ); ?></h2>
									<p><?php esc_html_e( 'Choose what customers see and what should happen after a match.', 'checkout-blocker-by-talistech' ); ?></p>
								</div>
							</div>
							<table class="form-table tcb-form-table" role="presentation">
								<?php do_settings_fields( Settings::PAGE_SLUG, self::BEHAVIOR_SECTION ); ?>
							</table>
						</section>

						<div class="tcb-save-bar">
							<p><?php esc_html_e( 'Changes apply to the next checkout attempt.', 'checkout-blocker-by-talistech' ); ?></p>
							<?php submit_button( __( 'Save settings', 'checkout-blocker-by-talistech' ), 'primary', 'submit', false ); ?>
						</div>
					</form>
				</main>

				<aside class="tcb-sidebar" aria-label="<?php esc_attr_e( 'Checkout Blocker help', 'checkout-blocker-by-talistech' ); ?>">
					<section class="tcb-card tcb-guide">
						<h2><?php esc_html_e( 'Quick start', 'checkout-blocker-by-talistech' ); ?></h2>
						<ol>
							<li><?php esc_html_e( 'Paste one or more rules into the matching list.', 'checkout-blocker-by-talistech' ); ?></li>
							<li><?php esc_html_e( 'Set the notice shown to blocked customers.', 'checkout-blocker-by-talistech' ); ?></li>
							<li><?php esc_html_e( 'Save, then test with a private browser window.', 'checkout-blocker-by-talistech' ); ?></li>
						</ol>
						<p class="tcb-callout"><?php esc_html_e( 'Rules are normalized when saved, so casing, accents and repeated spaces do not cause accidental misses.', 'checkout-blocker-by-talistech' ); ?></p>
					</section>

					<section class="tcb-card tcb-session-card">
						<span class="tcb-eyebrow"><?php esc_html_e( 'Account access', 'checkout-blocker-by-talistech' ); ?></span>
						<h2><?php esc_html_e( 'Destroy sessions', 'checkout-blocker-by-talistech' ); ?></h2>
						<p><?php esc_html_e( 'Invalidate sessions for customers with an exact email rule. Administrators and shop managers are protected. Domain and wildcard rules are ignored.', 'checkout-blocker-by-talistech' ); ?></p>
						<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
							<input type="hidden" name="action" value="talistech_checkout_blocker_destroy_sessions">
							<?php wp_nonce_field( 'talistech_checkout_blocker_destroy_sessions' ); ?>
							<?php submit_button( __( 'Destroy exact-email sessions', 'checkout-blocker-by-talistech' ), 'secondary', 'submit', false ); ?>
						</form>
					</section>
				</aside>
			</div>
		</div>
		<?php
	}

	public static function registerFields(): void {
		add_settings_section( self::RULES_SECTION, '', '__return_false', Settings::PAGE_SLUG );

		self::addTextAreaField(
			'email_rules',
			__( 'Blocked email addresses', 'checkout-blocker-by-talistech' ),
			__( 'Block one address, an entire domain, or an email pattern.', 'checkout-blocker-by-talistech' ),
			array(
				array( __( 'Exact email', 'checkout-blocker-by-talistech' ), 'blocked@example.com' ),
				array( __( 'Entire domain', 'checkout-blocker-by-talistech' ), '@example.com' ),
				array( __( 'Wildcard', 'checkout-blocker-by-talistech' ), 'test-*@example.net' ),
			)
		);
		self::addTextAreaField(
			'name_rules',
			__( 'Blocked full names', 'checkout-blocker-by-talistech' ),
			__( 'Match the complete billing first and last name. Accents, casing and repeated spaces are normalized.', 'checkout-blocker-by-talistech' ),
			array(
				array( __( 'Exact name', 'checkout-blocker-by-talistech' ), 'Jan Jansen' ),
				array( __( 'Any first name', 'checkout-blocker-by-talistech' ), '* De Smet' ),
			)
		);
		self::addTextAreaField(
			'address_rules',
			__( 'Blocked billing addresses', 'checkout-blocker-by-talistech' ),
			__( 'Use street and number, postcode, and two-letter country code separated by | characters.', 'checkout-blocker-by-talistech' ),
			array(
				array( __( 'Exact address', 'checkout-blocker-by-talistech' ), 'Keringstraat 7|9506|BE' ),
				array( __( 'Wildcard street', 'checkout-blocker-by-talistech' ), '*laan 10|2000|BE' ),
			)
		);

		add_settings_section( self::BEHAVIOR_SECTION, '', '__return_false', Settings::PAGE_SLUG );
		add_settings_field(
			'notice_text',
			__( 'Customer notice', 'checkout-blocker-by-talistech' ),
			array( self::class, 'renderTextField' ),
			Settings::PAGE_SLUG,
			self::BEHAVIOR_SECTION,
			array(
				'key'         => 'notice_text',
				'label_for'   => 'tcb-notice-text',
				'description' => __( 'Shown at checkout when an order is blocked.', 'checkout-blocker-by-talistech' ),
			)
		);
		add_settings_field(
			'notify_admins',
			__( 'Admin notifications', 'checkout-blocker-by-talistech' ),
			array( self::class, 'renderCheckbox' ),
			Settings::PAGE_SLUG,
			self::BEHAVIOR_SECTION,
			array(
				'key'       => 'notify_admins',
				'label_for' => 'tcb-notify-admins',
				'label'     => __( 'Email administrators with personal checkout details when a block occurs. Limited to one per IP per ten minutes and sixty per site per hour.', 'checkout-blocker-by-talistech' ),
			)
		);
		add_settings_field(
			'force_logout',
			__( 'Force logout', 'checkout-blocker-by-talistech' ),
			array( self::class, 'renderCheckbox' ),
			Settings::PAGE_SLUG,
			self::BEHAVIOR_SECTION,
			array(
				'key'       => 'force_logout',
				'label_for' => 'tcb-force-logout',
				'label'     => __( 'Log out signed-in customers whose account email matches a rule. Administrators and shop managers are protected.', 'checkout-blocker-by-talistech' ),
			)
		);
		add_settings_field(
			'logout_redirect',
			__( 'Logout redirect', 'checkout-blocker-by-talistech' ),
			array( self::class, 'renderTextField' ),
			Settings::PAGE_SLUG,
			self::BEHAVIOR_SECTION,
			array(
				'key'         => 'logout_redirect',
				'label_for'   => 'tcb-logout-redirect',
				'type'        => 'url',
				'description' => __( 'Local URL used after a forced logout.', 'checkout-blocker-by-talistech' ),
			)
		);
	}

	/**
	 * Render an escaped rule input and examples.
	 *
	 * @param array<string, mixed> $args
	 */
	public static function renderTextArea( array $args ): void {
		$settings      = Settings::get();
		$key           = (string) $args['key'];
		$id            = 'tcb-' . str_replace( '_', '-', $key );
		$descriptionId = $id . '-description';
		$examples      = is_array( $args['examples'] ?? null ) ? $args['examples'] : array();
		$placeholder   = implode(
			"\n",
			array_map(
				static fn( array $example ): string => (string) ( $example[1] ?? '' ),
				$examples
			)
		);
		?>
		<textarea
			class="large-text code tcb-rule-input"
			rows="6"
			id="<?php echo esc_attr( $id ); ?>"
			name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]"
			aria-describedby="<?php echo esc_attr( $descriptionId ); ?>"
			placeholder="<?php echo esc_attr( $placeholder ); ?>"
			spellcheck="false"
			autocapitalize="off"
		><?php echo esc_textarea( (string) ( $settings[ $key ] ?? '' ) ); ?></textarea>
		<div class="tcb-field-help" id="<?php echo esc_attr( $descriptionId ); ?>">
			<p><?php echo esc_html( (string) $args['description'] ); ?></p>
			<?php self::renderExamples( $examples ); ?>
		</div>
		<?php
	}

	/**
	 * Render an escaped settings input.
	 *
	 * @param array<string, mixed> $args
	 */
	public static function renderTextField( array $args ): void {
		$settings = Settings::get();
		$key      = (string) $args['key'];
		$id       = 'tcb-' . str_replace( '_', '-', $key );
		$type     = (string) ( $args['type'] ?? 'text' );
		?>
		<input
			class="regular-text"
			id="<?php echo esc_attr( $id ); ?>"
			type="<?php echo esc_attr( $type ); ?>"
			name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]"
			value="<?php echo esc_attr( (string) ( $settings[ $key ] ?? '' ) ); ?>"
		>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( (string) $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Render a settings checkbox.
	 *
	 * @param array<string, mixed> $args
	 */
	public static function renderCheckbox( array $args ): void {
		$settings = Settings::get();
		$key      = (string) $args['key'];
		$id       = 'tcb-' . str_replace( '_', '-', $key );
		?>
		<label class="tcb-checkbox" for="<?php echo esc_attr( $id ); ?>">
			<input
				id="<?php echo esc_attr( $id ); ?>"
				type="checkbox"
				name="<?php echo esc_attr( Settings::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]"
				value="1"
				<?php checked( ! empty( $settings[ $key ] ) ); ?>
			>
			<span><?php echo esc_html( (string) $args['label'] ); ?></span>
		</label>
		<?php
	}

	/**
	 * Render rule examples.
	 *
	 * @param list<array{0: string, 1: string}> $examples
	 */
	private static function renderExamples( array $examples ): void {
		if ( $examples === array() ) {
			return;
		}
		?>
		<div class="tcb-examples">
			<span class="tcb-examples__label"><?php esc_html_e( 'Examples', 'checkout-blocker-by-talistech' ); ?></span>
			<?php foreach ( $examples as $example ) : ?>
				<span class="tcb-example">
					<span><?php echo esc_html( $example[0] ); ?></span>
					<code><?php echo esc_html( $example[1] ); ?></code>
				</span>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Register a rule input with its description and examples.
	 *
	 * @param list<array{0: string, 1: string}> $examples
	 */
	private static function addTextAreaField( string $key, string $label, string $description, array $examples ): void {
		add_settings_field(
			$key,
			$label,
			array( self::class, 'renderTextArea' ),
			Settings::PAGE_SLUG,
			self::RULES_SECTION,
			array(
				'key'         => $key,
				'label_for'   => 'tcb-' . str_replace( '_', '-', $key ),
				'description' => $description,
				'examples'    => $examples,
			)
		);
	}
}
