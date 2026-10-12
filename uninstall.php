<?php
/**
 * WordPress uninstall entry point.
 *
 * @package TalistechCheckoutBlocker
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/src/Uninstall.php';
\Talistech\CheckoutBlocker\Uninstall::run();
