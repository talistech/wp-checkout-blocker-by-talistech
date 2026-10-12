<?php
/** Exercise the real WordPress settings error renderer and operational notice scope. */

use Talistech\CheckoutBlocker\{Admin, Plugin, Sessions, Settings};

if (wp_get_environment_type() !== 'local') {
    throw new RuntimeException('Admin compliance checks require a disposable local WordPress installation.');
}
require_once ABSPATH . 'wp-admin/includes/admin.php';

$checks = 0;
$check = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) { throw new RuntimeException('FAILED: ' . $label); }
    $checks++;
    WP_CLI::line('PASS ' . $label);
};
$render = static function (callable $callback): string {
    ob_start();
    $callback();
    return (string) ob_get_clean();
};
$administrator = get_user_by('login', 'tcb-test-admin');
$customer = get_user_by('login', 'tcb-test-customer');
if (!$administrator || !$customer) {
    throw new RuntimeException('Run the safety integration fixture before admin compliance checks.');
}
$previousUser = get_current_user_id();
$previousScreen = get_current_screen();
$previousErrors = $GLOBALS['wp_settings_errors'] ?? [];

try {
    wp_set_current_user($administrator->ID);
    $GLOBALS['wp_settings_errors'] = [];
    $sanitized = Settings::sanitize([
        'email_rules' => "<script>alert(1)</script>\nvalid@example.com",
        'address_rules' => '<img src=x onerror=alert(2)>',
    ]);
    $html = $render(static fn() => settings_errors(Settings::OPTION_KEY));
    $check($sanitized['email_rules'] === 'valid@example.com' && $sanitized['address_rules'] === '', 'Invalid HTML rules are skipped without losing a valid rule');
    $check(str_contains($html, '&lt;script&gt;') && str_contains($html, '&lt;img'), 'WordPress settings_errors displays invalid rules as escaped text');
    $check(!str_contains($html, '<script') && !str_contains($html, '<img'), 'No executable rule markup reaches the real settings error renderer');

    $missingDependency = !defined('WC_VERSION') || version_compare((string) WC_VERSION, '10.9', '<');
    foreach (['plugins', 'plugins-network', 'settings_page_' . Settings::PAGE_SLUG] as $screen) {
        set_current_screen($screen);
        $html = $render([Plugin::class, 'dependencyNotice']);
        $check($missingDependency ? str_contains($html, 'notice-error is-dismissible') : $html === '', 'Dependency notice is contextual or disappears when resolved on ' . $screen);
    }
    foreach (['dashboard', 'edit-post', 'upload'] as $screen) {
        set_current_screen($screen);
        $check($render([Plugin::class, 'dependencyNotice']) === '', 'Dependency notice does not appear on ' . $screen);
    }

    set_current_screen('plugins');
    wp_set_current_user($customer->ID);
    $check($render([Plugin::class, 'dependencyNotice']) === '', 'Customers cannot see plugin dependency notices');
    $controller = new Admin(new Sessions());
    $links = ['Existing link'];
    $check($controller->pluginLinks($links) === $links, 'Customers do not receive an inaccessible settings action link');
    wp_set_current_user($administrator->ID);
    $check(count($controller->pluginLinks($links)) === 2, 'Administrators receive the settings action link');

    $check($render([Plugin::class, 'legacyConflictNotice']) === '', 'Legacy conflict notice disappears when the legacy plugin is absent');
    if (!class_exists('TcbLegacyComplianceFixture')) {
        class TcbLegacyComplianceFixture {}
    }
    class_alias(TcbLegacyComplianceFixture::class, 'Talistech_Checkout_Blocker');
    foreach (['plugins', 'plugins-network', 'settings_page_' . Settings::PAGE_SLUG] as $screen) {
        set_current_screen($screen);
        $html = $render([Plugin::class, 'legacyConflictNotice']);
        $check(str_contains($html, 'notice-warning is-dismissible') && str_contains($html, 'Deactivate it'), 'Legacy conflict notice is actionable and dismissible on ' . $screen);
    }
    set_current_screen('dashboard');
    $check($render([Plugin::class, 'legacyConflictNotice']) === '', 'Legacy conflict notice does not hijack the dashboard');
    set_current_screen('plugins');
    wp_set_current_user($customer->ID);
    $check($render([Plugin::class, 'legacyConflictNotice']) === '', 'Customers cannot see legacy conflict notices');

    Plugin::boot();
    $callback = [Plugin::class, $missingDependency ? 'dependencyNotice' : 'legacyConflictNotice'];
    $check(has_action('admin_notices', $callback) !== false && has_action('network_admin_notices', $callback) !== false, 'Operational notices cover both site and network administration');
} finally {
    wp_set_current_user($previousUser);
    $GLOBALS['current_screen'] = $previousScreen;
    $GLOBALS['wp_settings_errors'] = $previousErrors;
}

WP_CLI::success("Admin compliance integration: $checks checks passed; WooCommerce " . ($missingDependency ? 'unavailable' : 'available') . '.');
