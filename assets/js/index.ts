import {createElement} from 'react';
import SolanaQrCodePayment from "./src/solana-qr-code-payment";
import SolanaWalletPayment from "./src/solana-wallet-payment";
import {SolanaPaymentWindow} from "./src/types";
import SolanaPaymentVerifier from "./src/solana-payment-verifier";
import SolanaErrorPresenter from "./src/solana-error-presenter";
import {createRoot} from "react-dom/client";

declare const window: SolanaPaymentWindow;

window.addEventListener('load', () => {
    const config = window.SOLANA_PAYMENT_CONFIG;

    window.SOLANA_ERROR_PRESENTER = SolanaErrorPresenter.init(config.errorContainerElementSelector, config.errorMessagesMapping);
    window.SOLANA_PAYMENT_VERIFIER = SolanaPaymentVerifier.init(config);

    SolanaQrCodePayment.init(config);

    const domContainer = document.querySelector(config.walletsElementSelector);
    const root = createRoot(domContainer);

    root.render(
        createElement(
            SolanaWalletPayment,
            {
                ...config
            }
        )
    );
});
