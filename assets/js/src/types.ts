import {Cluster} from "@solana/web3.js";
import SolanaPaymentVerifier from "./solana-payment-verifier";

export type SolanaPaymentConfigType = {
    transaction: {
        reference: string,
        recipient: string,
        splToken: string,
        amount: number,
        label: string,
        message: string,
        memo: string,
    },
    cluster: Cluster | string,
    verificationServiceUrl: string,
    verificationServiceInterval: number,
    verificationServiceTimeout: number,
    paymentNotificationEndpoint: string,
    timeoutTimerSelector: string,
    qrCodeElementSelector: string,
    walletsElementSelector: string,
}

export interface SolanaPaymentWindow extends Window {
    SOLANA_PAYMENT_CONFIG: SolanaPaymentConfigType;
    SOLANA_PAYMENT_VERIFIER: SolanaPaymentVerifier;
    SOLANA_PAY_WC_NONCE_CONFIG: { nonce: string };
}
