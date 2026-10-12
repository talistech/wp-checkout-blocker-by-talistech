<?php
/** Suppress outbound mail from disposable fixtures; never included in the release. */
if (!defined('ABSPATH')) { exit; }
add_filter('pre_wp_mail', static function ($result) {
    return wp_get_environment_type() === 'local' ? true : $result;
});
// Record actual gateway invocation in disposable tests; never distributed with the plugin.
add_filter('woocommerce_cod_process_payment_order_status', static function ($status, $order) {
    if (wp_get_environment_type() === 'local') {
        $order->update_meta_data('_tcb_test_gateway_calls', (int) $order->get_meta('_tcb_test_gateway_calls') + 1);
        $order->save_meta_data();
    }
    return $status;
}, 10, 2);
