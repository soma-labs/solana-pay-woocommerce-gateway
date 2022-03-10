<?php
//#! Load dependencies
//...

/**
 * Add the Gateway to WooCommerce
 **/
add_filter( 'woocommerce_payment_gateways', function ( $gateways = [] ) {
    $gateways[] = 'SolanaPayWooCommerceGateway';

    return $gateways;
} );

add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_wc_endpoint_url( 'order-received' ) ) {
        return;
    }

    wp_enqueue_script(
        'swpp-main-js',
        SP_WC_URI . '/assets/dist/js/solana-payment-gateway.js',
        [],
        SolanaPayWooCommerceGatewayPlugin::getAssetsVersion(),
        true
    );
} );
