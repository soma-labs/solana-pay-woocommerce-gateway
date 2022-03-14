import {SolanaPaymentConfigType, SolanaPaymentWindow} from "./types";
import SolanaTimeoutTimer from "./solana-timeout-timer";

declare const window: SolanaPaymentWindow;

export default class SolanaPaymentVerifier {
    private config: SolanaPaymentConfigType;
    private isWaitingForStoreResponse: boolean = false;
    private verificationTimer: any;
    private timeoutTimer: SolanaTimeoutTimer;

    constructor(config: SolanaPaymentConfigType) {
        this.config = config;

        this.verifyTransaction();
        this.timeoutTimer = new SolanaTimeoutTimer({
            timerSelector: this.config.timeoutTimerSelector,
            timeout: this.config.verificationServiceTimeout,
            timeoutCallback: this.clearVerificationTimer.bind(this)
        });
    }

    public verifyTransaction(): void {
        this.startVerification()
            .then(this.notifyStore.bind(this))
            .then(SolanaPaymentVerifier.handleStoreResponse)
            .catch(console.log);
    }

    private async startVerification(): Promise<any> {
        return new Promise((resolve, reject) => {
            this.verificationTimer = setInterval(async () => {
                try {
                    let queryParams = new URLSearchParams(this.getTransactionVerificationParameters());
                    let data = await (
                        await fetch(`${this.config.verificationServiceUrl}?${queryParams.toString()}`)
                    ).json();

                    if (data.success) {
                        this.clearVerificationTimer();
                        resolve(data);
                    }
                } catch (e: any) {
                    this.clearVerificationTimer();
                    reject(e);
                }
            }, this.config.verificationServiceInterval);
        });
    }

    public clearVerificationTimer(): void {
        this.timeoutTimer.stop();
        clearInterval(this.verificationTimer);
    }

    private notifyStore(): Promise<any> {
        if (this.isWaitingForStoreResponse === true) {
            return;
        }

        this.isWaitingForStoreResponse = true;

        const formData = new FormData();
        const parameters = this.getTransactionVerificationParameters();

        for (let key in parameters) {
            formData.append(key, parameters[key]);
        }

        formData.append('security', window.SOLANA_PAY_WC_NONCE_CONFIG.nonce);

        return fetch(this.config.paymentNotificationEndpoint, {
            method: 'POST',
            body: formData
        }).then(response => response.json());
    }

    private static handleStoreResponse(response: { errors?: any, redirectUrl? : string }): void {
        if (response.errors) {
            console.log(response.errors);
            return;
        }

        if (response.redirectUrl) {
            window.location.href = response.redirectUrl;
        }
    }

    private getTransactionVerificationParameters(): any {
        const transaction = this.config.transaction;

        let params: any = {
            reference: transaction.reference,
            recipient: transaction.recipient,
            amount: transaction.amount,
            label: transaction.label,
            message: transaction.message,
            memo: transaction.memo,
            cluster: this.config.cluster
        }

        if (transaction.splToken) {
            params.splToken = transaction.splToken.toString();
        }

        return params;
    }

    public static init(config: SolanaPaymentConfigType): SolanaPaymentVerifier {
        return new SolanaPaymentVerifier(config);
    }
}
