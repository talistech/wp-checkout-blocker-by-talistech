<?php
/** Local, synthetic demo data used only to capture publication screenshots. */
if ( ! defined( 'ABSPATH' ) || wp_get_environment_type() !== 'local' ) {
    throw new RuntimeException( 'Media setup is restricted to local WordPress.' );
}
update_option( 'blogname', 'Demo Shop' );
update_option( 'blogdescription', 'A local demonstration store' );
update_option( 'woocommerce_store_address', 'Voorbeeldstraat 10' );
update_option( 'woocommerce_store_city', 'Brussels' );
update_option( 'woocommerce_store_postcode', '1000' );
update_option( 'woocommerce_default_country', 'BE' );
update_option( 'woocommerce_currency', 'EUR' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'Cash on delivery', 'enable_for_virtual' => 'yes' ) );
update_option( 'talistech_checkout_blocker_settings', array_merge(
    Talistech\CheckoutBlocker\Settings::defaults(),
    array(
        'email_rules' => "blocked@example.com\n@example.org\ntest-*@example.net",
        'name_rules' => "Demo Customer\n* Example",
        'address_rules' => 'Example Street 10|1000|BE',
        'notice_text' => 'Checkout is not available for this account. Please contact support.',
        'notify_admins' => 0,
        'force_logout' => 0,
    )
) );
$product_id = (int) get_option( 'tcb_test_product_id' );
$product = wc_get_product( $product_id );
if ( ! $product ) {
    $product = new WC_Product_Simple();
}
$product->set_name( 'Demo product' );
$product->set_regular_price( '24.00' );
$product->set_virtual( true );
$product->set_status( 'publish' );
$product->save();
update_option( 'tcb_media_product_id', $product->get_id() );
foreach ( array( 'cart' => '[woocommerce_cart]', 'checkout' => '[woocommerce_checkout]' ) as $slug => $content ) {
    $page = get_page_by_path( $slug );
    $id = wp_insert_post( array(
        'ID' => $page ? $page->ID : 0,
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_name' => $slug,
        'post_title' => ucfirst( $slug ),
        'post_content' => $content,
    ) );
    update_option( 'woocommerce_' . $slug . '_page_id', $id );
    update_post_meta( $id, '_wp_page_template', 'template-fullwidth.php' );
}
update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();
echo 'Media demo prepared. Product ID: ' . (int) $product->get_id();
