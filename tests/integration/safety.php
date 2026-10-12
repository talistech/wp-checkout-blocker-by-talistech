<?php
/** Integration checks executed by WP-CLI in the disposable local environment. */

use Talistech\CheckoutBlocker\{Admin, Notifications, RuleMatcher, Sessions, Settings, Plugin};

if (wp_get_environment_type() !== 'local') {
    throw new RuntimeException('Integration checks require a disposable local WordPress installation.');
}
$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) { throw new RuntimeException('FAILED: ' . $label); }
    $checks++;
    WP_CLI::line('PASS ' . $label);
};
$user = static function (string $login, string $email, string $role): WP_User {
    $existing = get_user_by('login', $login);
    $id = $existing ? $existing->ID : wp_insert_user(['user_login' => $login, 'user_email' => $email, 'user_pass' => 'tcb-local-checkout-1!', 'role' => $role]);
    if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
    $result = new WP_User($id);
    $result->set_role($role);
    return $result;
};
add_filter('pre_wp_mail', static fn() => true);
$customer = $user('tcb-test-customer', 'blocked@example.com', 'customer');
$admin = $user('tcb-test-admin', 'admin-tcb@example.com', 'administrator');
$manager = $user('tcb-test-manager', 'manager-tcb@example.com', 'shop_manager');
$star = $user('tcb-test-star', 'wild*@example.com', 'customer');
foreach ([$customer, $admin, $manager, $star] as $identity) {
    WP_Session_Tokens::get_instance($identity->ID)->create(time() + HOUR_IN_SECONDS);
}
update_option(Settings::OPTION_KEY, array_merge(Settings::defaults(), ['email_rules' => "blocked@example.com\nadmin-tcb@example.com\nmanager-tcb@example.com\nwild*@example.com\n@example.com"]));
$check((new Sessions())->destroyExactEmailSessions() === 1, 'Only the exact customer account has its sessions destroyed');
$check(WP_Session_Tokens::get_instance($customer->ID)->get_all() === [], 'Customer sessions are invalidated');
foreach ([$admin, $manager, $star] as $identity) {
    $check(WP_Session_Tokens::get_instance($identity->ID)->get_all() !== [], 'Protected or wildcard account sessions survive');
}
$check(Settings::localRedirect('https://evil.test/') === home_url('/'), 'External redirect is rejected');
$check(Settings::localRedirect(home_url('/help')) === home_url('/help'), 'Local redirect is accepted');
$input = Settings::sanitize(['email_rules' => [], 'name_rules' => [], 'notify_admins' => ['1'], 'logout_redirect' => new stdClass()]);
$check($input['email_rules'] === '' && $input['notify_admins'] === 0, 'Malformed settings fail safely');
$existing = ['email_rules' => 'old@example.com', 'notice_text' => 'Old notice', 'notify_admins' => 0];
update_option(Settings::OPTION_KEY, $existing);
Settings::activate();
$check(get_option(Settings::OPTION_KEY) === $existing, 'Activation preserves 1.1.1 settings');
$check(has_action('woocommerce_after_checkout_validation') !== false && has_action('woocommerce_checkout_validate_order_before_payment') !== false, 'Both real WooCommerce validation hooks are registered');

update_option(Settings::OPTION_KEY, array_merge(Settings::defaults(), ['notify_admins' => 1]));
delete_option(Notifications::STATE_KEY);
delete_option(Notifications::LOCK_KEY);
remove_all_filters('pre_wp_mail');
$mailCount = 0;
$preMail = static function () use (&$mailCount): bool { $mailCount++; return true; };
add_filter('pre_wp_mail', $preMail);
$send = static function (string $ip, string $email = 'blocked@example.com'): void {
    (new Notifications())->maybeSend(['type' => 'email', 'rule' => '@example.com', 'value' => $email], ['email' => $email, 'ip' => $ip]);
};
$send('192.0.2.1');
$send('192.0.2.1', 'changed@example.com');
$check($mailCount === 1, 'Changing billing email does not reset the IP mail limit');
for ($i = 2; $i <= 61; $i++) { $send('192.0.2.' . $i); }
$check($mailCount === 60, 'The sixty-first notification is refused');
global $wpdb;
$lockName = 'tcb_notice_' . substr(hash('sha256', DB_NAME . '|' . $wpdb->options), 0, 48);
$check((string)$wpdb->get_var($wpdb->prepare('SELECT IS_FREE_LOCK(%s)', $lockName)) === '1', 'The notification lock is released');
delete_option(Notifications::STATE_KEY);
$otherConnection = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
$check((string)$otherConnection->get_var($otherConnection->prepare('SELECT GET_LOCK(%s, 0)', $lockName)) === '1', 'A second database connection owns the notification lock');
$send('192.0.2.1');
$check($mailCount === 60, "A request cannot steal another connection's lock");
$otherConnection->close();
$send('192.0.2.1');
$check($mailCount === 61, 'Disconnecting a crashed owner releases the lock automatically');
remove_filter('pre_wp_mail', $preMail);
delete_option(Notifications::STATE_KEY);
add_filter('pre_wp_mail', static function (): never { throw new RuntimeException('Deliberate local mail failure'); });
$send('192.0.2.1');
$check((string)$wpdb->get_var($wpdb->prepare('SELECT IS_FREE_LOCK(%s)', $lockName)) === '1' && get_option(Notifications::STATE_KEY)['count'] === 1, 'Mail exceptions are contained and retain the reserved limit');
remove_all_filters('pre_wp_mail');
add_filter('pre_wp_mail', static fn() => true);
delete_option(Notifications::STATE_KEY);

$adminController = new Admin(new Sessions());
wp_set_current_user($customer->ID);
ob_start();
$adminController->renderPage();
$check(ob_get_clean() === '', 'Customers cannot read the settings page');
$dieHandler = static function (): Closure {
    return static function ($message): never { throw new RuntimeException(wp_strip_all_tags((string) $message)); };
};
add_filter('wp_die_handler', $dieHandler);
add_filter('wp_die_ajax_handler', $dieHandler);
try {
    $adminController->destroySessions();
    $check(false, 'Customer cannot destroy sessions');
} catch (RuntimeException $exception) {
    $check(str_contains($exception->getMessage(), 'not allowed'), 'Session handler rejects customers');
}
wp_set_current_user($admin->ID);
$_REQUEST['_wpnonce'] = 'invalid';
try {
    $adminController->destroySessions();
    $check(false, 'Invalid nonce rejected');
} catch (RuntimeException $exception) {
    $check(true, 'Session handler rejects invalid administrator nonces');
}
unset($_REQUEST['_wpnonce']);
remove_filter('wp_die_handler', $dieHandler);
remove_filter('wp_die_ajax_handler', $dieHandler);
$check(has_action('admin_init', [Plugin::class, 'privacyPolicy']) !== false, 'Privacy policy guidance is registered');
wp_set_current_user(0);

$productId = (int) get_option('tcb_test_product_id', 0);
$product = $productId ? wc_get_product($productId) : false;
if (!$product) {
    $product = new WC_Product_Simple();
    $product->set_name('TCB verification product');
    $product->set_status('publish');
    $product->set_virtual(true);
    $product->set_regular_price('10');
    $productId = $product->save();
    update_option('tcb_test_product_id', $productId);
}
$pageId = (int) get_option('woocommerce_checkout_page_id');
if ($pageId <= 0) { $pageId = wp_insert_post(['post_title' => 'Checkout', 'post_name' => 'checkout', 'post_status' => 'publish', 'post_type' => 'page']); }
wp_update_post(['ID' => $pageId, 'post_content' => '[woocommerce_checkout]']);
update_option('woocommerce_checkout_page_id', $pageId);
update_option('woocommerce_enable_guest_checkout', 'yes');
update_option('woocommerce_coming_soon', 'no');
update_option('woocommerce_store_pages_only', 'no');
update_option('woocommerce_cod_settings', ['enabled' => 'yes', 'title' => 'Cash on delivery', 'enable_for_virtual' => 'yes']);
update_option('woocommerce_store_address', 'Test street 1');
update_option('woocommerce_store_postcode', '1000');
update_option('woocommerce_default_country', 'BE');
update_option('woocommerce_currency', 'EUR');
update_option('woocommerce_calc_taxes', 'no');
update_option(Settings::OPTION_KEY, Settings::defaults());
WP_CLI::success("Safety integration: $checks checks passed. Fixture product: $productId.");
