<?php
/** Exercise activation, new sites, deactivation and actual uninstall in a local network. */
if (wp_get_environment_type() !== 'local' || !is_multisite() || !defined('TCB_MULTISITE_PHASE')) {
    throw new RuntimeException('This test requires the disposable local multisite test network.');
}
$check = static function (bool $condition, string $label): void {
    if (!$condition) { throw new RuntimeException('FAILED: ' . $label); }
    WP_CLI::line('PASS ' . $label);
};
$settingsKey = 'talistech_checkout_blocker_settings';
$stateKey = 'talistech_checkout_blocker_notifications';
$lockKey = 'talistech_checkout_blocker_notification_lock';
$domain = wp_parse_url(home_url(), PHP_URL_HOST);
$create = static function (string $path) use ($domain): int {
    $id = wpmu_create_blog($domain, $path, 'TCB lifecycle fixture', 1, [], get_current_network_id());
    if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
    return (int)$id;
};
if (TCB_MULTISITE_PHASE === 'prepare') {
    $ids = [get_current_blog_id(), $create('/tcb-existing/'), $create('/tcb-empty/')];
    update_option($settingsKey, ['email_rules' => 'retained@example.com']);
    switch_to_blog($ids[1]);
    update_option($settingsKey, ['email_rules' => 'second@example.com']);
    restore_current_blog();
    update_site_option('tcb_test_sites', $ids);
    WP_CLI::success('Multisite fixtures prepared.');
    return;
}
$ids = get_site_option('tcb_test_sites');
if (TCB_MULTISITE_PHASE === 'active') {
    $check(get_option($settingsKey)['email_rules'] === 'retained@example.com', 'Network activation retains existing primary rules');
    switch_to_blog($ids[1]);
    $check(get_option($settingsKey)['email_rules'] === 'second@example.com', 'Network activation retains existing secondary rules');
    restore_current_blog();
    switch_to_blog($ids[2]);
    $check(is_array(get_option($settingsKey)), 'Network activation initializes an existing empty site');
    restore_current_blog();
    $ids[] = $create('/tcb-new/');
    update_site_option('tcb_test_sites', $ids);
    switch_to_blog(end($ids));
    $check(is_array(get_option($settingsKey)), 'Network activation initializes a newly created site');
    restore_current_blog();
    foreach ($ids as $id) {
        switch_to_blog($id);
        update_option($stateKey, ['count' => 1]);
        update_option($lockKey, time() - 30);
        set_transient('tcb_notice_legacy_fixture', 1, 600);
        update_option('tcb_test_unrelated', 'retain');
        restore_current_blog();
    }
    $customer = get_user_by('login', 'tcb-test-customer');
    WP_Session_Tokens::get_instance($customer->ID)->destroy_all();
    WP_Session_Tokens::get_instance($customer->ID)->create(time() + 3600);
    update_site_option('tcb_test_customer', $customer->ID);
    update_site_option('tcb_test_orders', wc_get_orders(['limit' => 1, 'paginate' => true])->total);
} elseif (TCB_MULTISITE_PHASE === 'deactivated') {
    foreach ($ids as $id) {
        switch_to_blog($id);
        $check(is_array(get_option($settingsKey)) && get_option($stateKey)['count'] === 1, 'Deactivation retains site ' . $id . ' settings');
        restore_current_blog();
    }
} elseif (TCB_MULTISITE_PHASE === 'uninstalled') {
    foreach ($ids as $id) {
        switch_to_blog($id);
        $check(get_option($settingsKey, null) === null && get_option($stateKey, null) === null && get_option($lockKey, null) === null, 'Uninstall removes site ' . $id . ' settings and limits');
        $check(get_transient('tcb_notice_legacy_fixture') === false, 'Uninstall removes legacy notification transients on site ' . $id);
        $check(get_option('tcb_test_unrelated') === 'retain', 'Uninstall retains unrelated data on site ' . $id);
        restore_current_blog();
    }
    $customerId = get_site_option('tcb_test_customer');
    $check(get_user_by('id', $customerId) instanceof WP_User, 'Uninstall retains customer accounts');
    $check(count(WP_Session_Tokens::get_instance($customerId)->get_all()) === 1, 'Uninstall retains customer sessions');
    $check((int)wc_get_orders(['limit' => 1, 'paginate' => true])->total === (int)get_site_option('tcb_test_orders'), 'Uninstall retains WooCommerce orders');
} else {
    throw new RuntimeException('Unknown multisite test phase');
}
WP_CLI::success('Multisite lifecycle phase ' . TCB_MULTISITE_PHASE . ' passed.');
