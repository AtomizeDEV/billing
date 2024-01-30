import EmberStripeService from '@adopted-ember-addons/ember-stripe-elements/services/stripev3';
import Service from '@ember/service';
import PaymentGatewayModel from '../models/payment-gateway';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { computed, get } from '@ember/object';
import { isBlank } from '@ember/utils';
import { assert } from '@ember/debug';
import config from 'ember-get-config';
import fetch from 'fetch';

export default class StripeService extends EmberStripeService {
    @service store;
    @tracked stripeGateway;
    @tracked publishableKey;
    @tracked stripeOptions = {};
    @tracked stripeInitialized = false;
    @tracked _elements = [];

    @computed('stripeInitialized') get instance() {
        if (!window.stripeInstance) {
            this.getStripeInstance();
        }

        assert('Stripe must be loaded.', Boolean(window.stripeInstance));
        return window.stripeInstance;
    }

    constructor() {
        super(...arguments);
        this.createStripeInstance();
    }

    configure() {}

    async createStripeInstance() {
        if (window.stripeInstance) {
            return window.stripeInstance;
        }

        let stripeGateway;

        if (this.store instanceof Service) {
            stripeGateway = await this.loadStripeGateway();
        } else {
            stripeGateway = await this.loadStripeGatewayUsingFetch();
        }

        if (isBlank(stripeGateway) || stripeGateway.api_key === null) {
            return;
        }

        this.stripeGateway = stripeGateway;
        this.publishableKey = stripeGateway.api_key;

        if (!isBlank(stripeGateway.options)) {
            this.stripeOptions = stripeGateway.options;
        }

        window.stripeInstance = new Stripe(stripeGateway.api_key, this.stripeOptions);
        this.stripeInitialized = true;

        return window.stripeInstance;
    }

    getStripeInstance() {
        if (window.stripeInstance) {
            return window.stripeInstance;
        }

        const stripeGateway = this.getStripeGateway();

        this.stripeGateway = stripeGateway;
        this.publishableKey = stripeGateway.api_key;

        if (!isBlank(stripeGateway.options)) {
            this.stripeOptions = stripeGateway.options;
        }

        window.stripeInstance = new Stripe(stripeGateway.api_key, this.stripeOptions);
        this.stripeInitialized = true;

        return window.stripeInstance;
    }

    getStripeGateway() {
        let paymentGateway = null;

        // check if already loaded
        if (this.isPaymentGateway(this.stripeGateway)) {
            paymentGateway = this.stripeGateway;
        }

        // attempt to load from store
        try {
            paymentGateway = this.store.peekAll('payment-gateway').find((gateway) => {
                return gateway.code === 'stripe';
            });
        } catch (error) {
            // do nothing
        }

        return paymentGateway;
    }

    loadStripeGateway() {
        return new Promise((resolve) => {
            let paymentGateway;

            paymentGateway = this.getStripeGateway();

            if (paymentGateway) {
                return resolve(paymentGateway);
            }

            try {
                return this.store
                    .queryRecord('payment-gateway', { code: 'stripe', single: true })
                    .then((paymentGateway) => {
                        resolve(paymentGateway);
                    })
                    .catch(() => {
                        resolve(paymentGateway);
                    });
            } catch (error) {
                throw new Error('Unable to load payment gateway using the store service.');
            }
        });
    }

    /**
     * During app instance initialization the store or fetch
     * service might not be initialized, load using fetch for a dependable initialization
     *
     * @memberof StripeService
     */
    loadStripeGatewayUsingFetch() {
        let host = get(config, 'API.host');
        let url = `${host}/billing/int/v1/payment-gateways?code=stripe&single=true`;

        return new Promise((resolve) => {
            return fetch(url, {
                method: 'GET',
                mode: 'cors',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                    Authorization: `Bearer ${this._getAuthenticationToken()}`,
                },
            }).then((response) => {
                if (response.ok) {
                    return response.json().then((json) => {
                        const { paymentGateway } = json;

                        if (paymentGateway) {
                            resolve(paymentGateway);
                        } else {
                            resolve(null);
                        }
                    });
                }

                resolve(null);
            });
        });
    }

    _getAuthenticationToken() {
        const localStorageSession = JSON.parse(window.localStorage.getItem('ember_simple_auth-session'));

        if (localStorageSession) {
            let { authenticated } = localStorageSession;
            let token = authenticated.token;

            return token;
        }

        return null;
    }

    isPaymentGateway(paymentGateway) {
        return paymentGateway instanceof PaymentGatewayModel;
    }

    addStripeElement(element) {
        this._elements.pushObject(element);
    }

    removeStripeElement(element) {
        this._elements.removeObject(element);
    }

    getActiveElements() {
        return [...this._elements];
    }

    /**
     * @see https://stripe.com/docs/js/elements_object/create
     */
    elements() {
        return this.instance.elements(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/checkout/redirect_to_checkout
     */
    redirectToCheckout() {
        return this.instance.redirectToCheckout(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_intents/confirm_card_payment
     */
    confirmCardPayment() {
        return this.instance.confirmCardPayment(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_intents/confirm_alipay_payment
     */
    confirmAlipayPayment() {
        return this.instance.confirmAlipayPayment(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_intents/confirm_au_becs_debit_payment
     */
    confirmAuBecsDebitPayment() {
        return this.instance.confirmAuBecsDebitPayment(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_intents/confirm_bancontact_payment
     */
    confirmBancontactPayment() {
        return this.instance.confirmBancontactPayment(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_intents/confirm_eps_payment
     */
    confirmEpsPayment() {
        return this.instance.confirmEpsPayment(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_intents/confirm_fpx_payment
     */
    confirmFpxPayment() {
        return this.instance.confirmFpxPayment(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_intents/confirm_giropay_payment
     */
    confirmGiropayPayment() {
        return this.instance.confirmGiropayPayment(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_intents/confirm_grabpay_payment
     */
    confirmGrabPayPayment() {
        return this.instance.confirmGrabPayPayment(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_intents/confirm_ideal_payment
     */
    confirmIdealPayment() {
        return this.instance.confirmIdealPayment(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_intents/confirm_oxxo_payment
     */
    confirmOxxoPayment() {
        return this.instance.confirmOxxoPayment(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_intents/confirm_p24_payment
     */
    confirmP24Payment() {
        return this.instance.confirmP24Payment(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_intents/confirm_sepa_debit_payment
     */
    confirmSepaDebitPayment() {
        return this.instance.confirmSepaDebitPayment(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_intents/confirm_sofort_payment
     */
    confirmSofortPayment() {
        return this.instance.confirmSofortPayment(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_intents/handle_card_action
     */
    handleCardAction() {
        return this.instance.handleCardAction(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_intents/retrieve_payment_intent
     */
    retrievePaymentIntent() {
        return this.instance.retrievePaymentIntent(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/setup_intents/confirm_card_setup
     */
    confirmCardSetup() {
        return this.instance.confirmCardSetup(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/setup_intents/confirm_au_becs_debit_setup
     */
    confirmAuBecsDebitSetup() {
        return this.instance.confirmAuBecsDebitSetup(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/setup_intents/confirm_bacs_debit_setup
     */
    confirmBacsDebitSetup() {
        return this.instance.confirmBacsDebitSetup(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/setup_intents/confirm_bancontact_setup
     */
    confirmBancontactSetup() {
        return this.instance.confirmBancontactSetup(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/setup_intents/confirm_ideal_setup
     */
    confirmIdealSetup() {
        return this.instance.confirmIdealSetup(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/setup_intents/confirm_sepa_debit_setup
     */
    confirmSepaDebitSetup() {
        return this.instance.confirmSepaDebitSetup(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/setup_intents/confirm_sofort_setup
     */
    confirmSofortSetup() {
        return this.instance.confirmSofortSetup(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/setup_intents/retrieve_setup_intent
     */
    retrieveSetupIntent() {
        return this.instance.retrieveSetupIntent(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_methods/create_payment_method
     */
    createPaymentMethod() {
        return this.instance.createPaymentMethod(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/payment_request/create
     */
    paymentRequest() {
        return this.instance.paymentRequest(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/tokens_sources/create_token
     */
    createToken() {
        return this.instance.createToken(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/tokens_sources/create_source
     */
    createSource() {
        return this.instance.createSource(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/tokens_sources/retrieve_source
     */
    retrieveSource() {
        return this.instance.retrieveSource(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/deprecated/handle_card_payment_element
     * @deprecated
     */
    handleCardPayment() {
        return this.instance.handleCardPayment(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/deprecated/confirm_payment_intent_element
     * @deprecated
     */
    confirmPaymentIntent() {
        return this.instance.confirmPaymentIntent(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/deprecated/handle_card_setup_element
     * @deprecated
     */
    handleCardSetup() {
        return this.instance.handleCardSetup(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/deprecated/confirm_setup_intent_element
     * @deprecated
     */
    confirmSetupIntent() {
        return this.instance.confirmSetupIntent(...arguments);
    }

    /**
     * @see https://stripe.com/docs/js/deprecated/handle_fpx_payment
     * @deprecated
     */
    handleFpxPayment() {
        return this.instance.handleFpxPayment(...arguments);
    }
}
