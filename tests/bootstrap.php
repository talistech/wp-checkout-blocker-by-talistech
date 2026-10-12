<?php

declare(strict_types=1);

require_once __DIR__ . '/wordpress-stubs.php';
define('Talistech\\CheckoutBlocker\\TCB_PLUGIN_FILE', dirname(__DIR__) . '/talistech-checkout-blocker-for-woocommerce.php');
define('Talistech\\CheckoutBlocker\\TCB_VERSION', '1.2.1');
require_once dirname(__DIR__) . '/src/RuleMatcher.php';
require_once dirname(__DIR__) . '/src/Settings.php';
require_once dirname(__DIR__) . '/src/Sessions.php';
require_once dirname(__DIR__) . '/src/Notifications.php';
require_once dirname(__DIR__) . '/src/WooCommerce.php';
require_once dirname(__DIR__) . '/src/Admin.php';
require_once dirname(__DIR__) . '/src/Plugin.php';
