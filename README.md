# Solana Pay WooCommerce Payment Gateway
Merchant integration of Solana Pay into WooCommerce. For more information: https://docs.solanapay.com/core/merchant-integration.
Requires separate service for reference generation and transaction validation. Currently using: https://github.com/soma-social/sol-merchant-server setup as a Cloudflare worker.

### Custom hooks
* Filter: **apply_filters( 'wc_solana_icon_url', string $url )**
  * SolanaPayWooCommerceGateway::__construct()
  * **$url** The URL to the Payment Method image. This will be shown to users in the Checkout page

* Filter: **apply_filters( 'wc_solana_admin_fields', array $fields )**
  * SolanaPayWooCommerceGateway::init_form_fields()
  * **$fields** The list of fields to add/update to the Payment Method in the Admin screen

* Filter: **apply_filters( 'wc_solana_transaction_amount', float $amount, WC_Order $order )**
  * SolanaPayWooCommerceGateway::get_solana_transaction_amount()
  * **$amount** The transaction amount. Default: order total. See: https://docs.solanapay.com/spec#amount
  * **$order** The order instance.

* Filter: **apply_filters( 'wc_solana_transaction_label', string $label, WC_Order $order )**
  * SolanaPayWooCommerceGateway::get_solana_transaction_label()
  * **$label** The transaction label. Default: shop name. See: https://docs.solanapay.com/spec#label
  * **$order** The order instance.

* Filter: **apply_filters( 'wc_solana_transaction_message', string $message, WC_Order $order )**
  * SolanaPayWooCommerceGateway::get_solana_transaction_message()
  * **$message** The transaction message. Default: empty string. See: https://docs.solanapay.com/spec#message
  * **$order** The order instance.

* Filter: **apply_filters( 'wc_solana_transaction_memo', string $memo, WC_Order $order )**
  * SolanaPayWooCommerceGateway::get_solana_transaction_memo()
  * **$memo** The transaction memo. Default: empty string. See: https://docs.solanapay.com/spec#memo
  * **$order** The order instance.

* Filter: **apply_filters( 'wc_solana_payment_html', string $html, WC_Order $order, array $solanaPaymentConfig )**
  * SolanaPayWooCommerceGateway::thank_you_page()
  * **$html** The checkout payment HTML.
  * **$order** The order instance.
  * **$solanaPaymentConfig** Configuration array for solana payment transaction.

* Filter: **apply_filters( 'wc_solana_payment_email', $emailInstructions, $order, $sent_to_admin )**
  * SolanaPayWooCommerceGateway::email_instructions()
  * **$emailInstructions** E-mail instructions text.
  * **$order** The order instance.
  * **$sent_to_admin** Whether e-mail is sent to admin.
