<?php

//#! Exit if this file is accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class SolanaPayWooCommerceGateway extends WC_Payment_Gateway
{
    private const DEVNET_CLUSTER = 'devnet';
    private const MAINNET_CLUSTER = 'mainnet-beta';
    private const CLUSTERS = [
        self::DEVNET_CLUSTER,
        self::MAINNET_CLUSTER,
    ];

    /**
     * Our plugin allows users to pay for WooCommerce orders with USD Coin, a token on the Solana blockchain.
     * We have chosen this token because of it's 1:1 parity with the USD which is one of the fiat currencies
     * supported by WooCommerce.
     *
     * This is the USD Coin token address on the Solana blockchain.
     * This is neither a public nor a private key and is not related to any user data.
     * For more information: https://explorer.solana.com/address/EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v
     */
    private const DEFAULT_SPL_TOKEN = 'EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v'; // USD Coin

    private const SOLANA_REFERENCE_META_KEY = 'solana_reference';

    private const DEFAULT_VERIFICATION_SERVICE_URL = 'https://solana-payment-verifier.soma-labs.workers.dev/';
    private const TRANSACTION_VERIFICATION_INTERVAL = 3000; // 3 seconds in  milliseconds
    private const TRANSACTION_VERIFICATION_TIMEOUT = 180000; // 3 minutes in milliseconds

    public function __construct()
    {
        $this->id                 = 'solana_pay_gateway';
        $this->icon               = apply_filters('wc_solana_icon_url', SP_WC_URI . '/assets/images/solpay-logo.svg?v=1' );
        $this->has_fields         = false;
        $this->method_title       = __( 'Solana Payment', '' );
        $this->method_description = __(
            'Allows payments using SolanaPay SDK using USD Coin as a default SPL token. When in development mode, payments will be made in SOL ($1 = 1SOL)',
            'solana-pay-woocommerce-gateway'
        );

        $this->enabled         = $this->get_option( 'enabled' );
        $this->title           = $this->get_option( 'title' );
        $this->description     = $this->get_option( 'description' );
        $this->instructions    = $this->get_option( 'instructions', $this->description );
        $this->merchant_wallet = $this->get_option( 'merchant_wallet' );

        $this->init_form_fields();
        $this->init_settings();

        add_action( "woocommerce_update_options_payment_gateways_$this->id", [ $this, 'process_admin_options' ] );
        add_action( "woocommerce_thankyou_$this->id", [ $this, 'thank_you_page' ] );
        add_action( "woocommerce_api_$this->id", [ $this, 'check_solana_payment' ] );
        add_action( 'woocommerce_email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );
    }

    public function init_form_fields(): void
    {
        $this->form_fields = apply_filters( 'wc_solana_admin_fields', [
            'enabled'                  => [
                'title'   => __( 'Enable/Disable', 'solana-pay-woocommerce-gateway' ),
                'type'    => 'checkbox',
                'label'   => __( 'Enable or disable Solana Payment Gateway', 'solana-pay-woocommerce-gateway' ),
                'default' => 'no',
            ],
            'title'                    => [
                'title'       => __( 'Solana Payment Gateway', 'solana-pay-woocommerce-gateway' ),
                'type'        => 'text',
                'description' => __( 'Add a new title for Solana Payment Gateway. This is what users see in the checkout page',
                                     'solana-pay-woocommerce-gateway' ),
                'default'     => __( 'Solana Payment Gateway', 'solana-pay-woocommerce-gateway' ),
                //'desc_tip'    => true,
            ],
            'description'              => [
                'title'       => __( 'Solana Payment Gateway description', 'solana-pay-woocommerce-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'Add a new description for Solana Payment Gateway. This is what users see in the checkout page',
                                     'solana-pay-woocommerce-gateway' ),
                'default'     => __( 'Solana Payment Gateway', 'solana-pay-woocommerce-gateway' ),
                //'desc_tip'    => true,
            ],
            'instructions'             => [
                'title'       => __( 'Instructions', 'solana-pay-woocommerce-gateway' ),
                'type'        => 'textarea',
                'description' => __( 'This will be added to the the order email.',
                                     'solana-pay-woocommerce-gateway' ),
                'default'     => __( 'Default instructions', 'solana-pay-woocommerce-gateway' ),
                //'desc_tip'    => true,
            ],
            'devmode'                  => [
                'title'       => __( 'Enable development mode', 'solana-pay-woocommerce-gateway' ),
                'type'        => 'checkbox',
                'description' => __( 'Transactions will take place on the Solana devnet.', 'solana-pay-woocommerce-gateway' ),
                'default'     => 'yes',
                //'desc_tip'    => true,
            ],
            'merchant_wallet'          => [
                'title'       => __( 'Merchant Solana wallet', 'solana-pay-woocommerce-gateway' ),
                'type'        => 'text',
                'description' => __( 'Merchant Solana wallet. When in development mode use devnet wallet',
                                     'solana-pay-woocommerce-gateway' ),
                'default'     => __( 'Merchant Solana wallet', 'solana-pay-woocommerce-gateway' ),
                //'desc_tip'    => true,
            ],
            'verification_service_url' => [
                'title'       => __( 'Verification service URL', 'solana-pay-woocommerce-gateway' ),
                'type'        => 'text',
                'description' => __( 'URL of the transaction verification service.', 'solana-pay-woocommerce-gateway' ),
                'default'     => self::DEFAULT_VERIFICATION_SERVICE_URL,
                //'desc_tip'    => true,
            ],
        ] );
    }

    public function thank_you_page( $order_id ): void
    {
        /** @var WC_Order $order */
        $order = wc_get_order( $order_id );
        $solanaPaymentConfig = $this->get_solana_payment_config( $order );

        ob_start();
        include( SP_WC_DIR . '/templates/wc-solana-payment.php' );
        $html = ob_get_clean();

        echo "<script>SOLANA_PAYMENT_CONFIG = " . wp_json_encode( $solanaPaymentConfig ) . ";</script>"
             . wp_kses(
                 apply_filters( 'wc_solana_payment_html', $html, $order, $solanaPaymentConfig ),
                 'post'
             );
    }

    public function process_payment( $order_id ): array
    {
        $order = wc_get_order( $order_id );
        $order->update_status( 'pending-payment', __( 'Awaiting payment confirmation', 'solana-pay-woocommerce-gateway' ) );

        wc_empty_cart();

        return [
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        ];
    }

    /**
     * @throws JsonException
     */
    public function check_solana_payment(): void
    {
        check_ajax_referer( 'check_solana_payment_nonce', 'security' );

        $errors = [];

        foreach ( [ 'reference', 'recipient', 'amount' ] as $param ) {
            if ( empty( $_POST[ $param ] ) ) {
                $errors[ $param ] = "$param missing";
            }
        }

        if ( ! empty( $_POST['cluster'] ) && ! in_array( $_POST['cluster'], self::CLUSTERS, true ) ) {
            $errors['cluster'] = 'Invalid cluster';
        }

        if ( ! empty( $errors ) ) {
            wp_send_json( [ 'errors' => $errors ] );

            return;
        }

        $reference = sanitize_text_field( $_POST['reference'] );
        $recipient = sanitize_text_field( $_POST['recipient'] );
        $splToken = !empty( $_POST['splToken'] ) ? sanitize_text_field( $_POST['splToken'] ) : '';
        $amount = sanitize_text_field( $_POST['amount'] );
        $label = sanitize_text_field( $_POST['label'] );
        $message = sanitize_text_field( $_POST['message'] );
        $memo = sanitize_text_field( $_POST['memo'] );
        $cluster = sanitize_text_field( $_POST['cluster'] );

        $orderWithReference = get_posts(
            [
                'post_type'      => 'shop_order',
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'meta_key'       => self::SOLANA_REFERENCE_META_KEY,
                'meta_value'     => $reference,
            ]
        );

        if ( empty( $orderWithReference ) ) {
            wp_send_json( [ 'errors' => [ 'order' => "No order found for reference: {$reference}" ] ] );

            return;
        }

        /** @var WC_Order $order */
        $order = wc_get_order( $orderWithReference[0]->ID );

        $response = wp_remote_get(
            add_query_arg(
                [
                    'reference' => $reference,
                    'recipient' => $recipient,
                    'splToken'  => $splToken,
                    'amount'    => $amount,
                    'label'     => $label,
                    'message'   => $message,
                    'memo'      => $memo,
                    'cluster'   => $cluster,
                ],
                $this->get_option( 'verification_service_url' )
            )
        );

        if ( $response instanceof WP_Error ) {
            wp_send_json( [ 'errors' => [ 'request' => 'Verification request failed' ] ] );

            return;
        }

        $decodedBody = json_decode( $response['body'], true, 512, JSON_THROW_ON_ERROR );

        if ( array_key_exists( 'success', $decodedBody ) && $decodedBody['success'] === true ) {
            $order->payment_complete();

            wp_send_json( [ 'redirectUrl' => $order->get_view_order_url() ] );

            return;
        }

        wp_send_json( $decodedBody );
    }

    public function email_instructions( WC_Order $order, $sent_to_admin, $plain_text = false ): void
    {
        $emailInstructions = '';

        if ( ! $sent_to_admin && $this->id === $order->get_payment_method() ) {
            if ( $this->instructions ) {
                $emailInstructions .= wp_kses_post( wpautop( wptexturize( $this->instructions ) ) . PHP_EOL );
            }

            echo apply_filters( 'wc_solana_payment_email', $emailInstructions, $order, $sent_to_admin );
        }
    }

    private function get_solana_payment_config( WC_Order $order ): array
    {
        if (
            ( $solanaReference = get_post_meta( $order->get_id(), self::SOLANA_REFERENCE_META_KEY, true ) )
            && ! empty( $solanaReference )
        ) {
            $reference = esc_attr( $solanaReference );
        } else {
            $reference = esc_attr( $this->get_solana_payment_reference() );
            update_post_meta( $order->get_id(), self::SOLANA_REFERENCE_META_KEY, $reference );
        }

        $merchantWallet = esc_html( $this->get_option( 'merchant_wallet' ) );
        $splToken       = $this->get_option( 'devmode' ) === 'yes' ? '' : self::DEFAULT_SPL_TOKEN;
        $amount         = esc_html( $this->get_solana_transaction_amount( $order ) );
        $label          = esc_html( $this->get_solana_transaction_label( $order ) );
        $message        = esc_html( $this->get_solana_transaction_message( $order ) );
        $memo           = esc_html( $this->get_solana_transaction_memo( $order ) );

        $cluster                     = $this->get_option( 'devmode' ) === 'yes' ? self::DEVNET_CLUSTER : self::MAINNET_CLUSTER;
        $verificationServiceUrl      = esc_html( $this->get_option( 'verification_service_url' ) );
        $paymentNotificationEndpoint = WC()->api_request_url( $this->id );

        // TODO: Consider creating custom '/wc-api/solana-transaction-data' endpoint for fetching transaction data.
        return [
            'transaction'                 => [
                'reference' => $reference,
                'recipient' => $merchantWallet,
                'splToken'  => $splToken,
                'amount'    => $amount,
                'label'     => $label,
                'message'   => $message,
                'memo'      => $memo,
            ],
            'cluster'                     => $cluster,
            'verificationServiceUrl'      => $verificationServiceUrl,
            'verificationServiceInterval' => self::TRANSACTION_VERIFICATION_INTERVAL,
            'verificationServiceTimeout'  => self::TRANSACTION_VERIFICATION_TIMEOUT,
            'paymentNotificationEndpoint' => $paymentNotificationEndpoint,
            'timeoutTimerSelector'        => '.js-solana-timeout-timer',
            'qrCodeElementSelector'       => '.js-solana-qr-container',
            'walletsElementSelector'      => '.js-solana-wallet-container',
        ];
    }

    /**
     * Details: https://docs.solanapay.com/spec#reference
     */
    private function get_solana_payment_reference(): string
    {
        $response = wp_remote_get( $this->get_option( 'verification_service_url' ) . '/reference' );

        if ( $response instanceof WP_Error ) {
            return '';
        }

        try {
            $decodedBody = json_decode( $response['body'], true, 512, JSON_THROW_ON_ERROR );
        } catch ( JsonException $e ) {
            return '';
        }

        return $decodedBody['reference'];
    }

    /**
     * Details: https://docs.solanapay.com/spec#amount
     */
    private function get_solana_transaction_amount( WC_Order $order ): float
    {
        return apply_filters( 'wc_solana_transaction_amount', $order->get_total(), $order );
    }

    /**
     * Details: https://docs.solanapay.com/spec#label
     */
    private function get_solana_transaction_label( WC_Order $order ): string
    {
        return urlencode( apply_filters( 'wc_solana_transaction_label', get_bloginfo( 'name' ), $order ) );
    }

    /**
     * Details: https://docs.solanapay.com/spec#message
     */
    private function get_solana_transaction_message( WC_Order $order ): string
    {
        return urlencode( apply_filters( 'wc_solana_transaction_message', '', $order ) );
    }

    /**
     * Details: https://docs.solanapay.com/spec#memo
     */
    private function get_solana_transaction_memo( WC_Order $order ): string
    {
        return urlencode( apply_filters( 'wc_solana_transaction_memo', '', $order ) );
    }
}
