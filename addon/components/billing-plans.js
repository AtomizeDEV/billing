import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action, computed } from '@ember/object';

export default class BillingPlansComponent extends Component {
    @service store;
    @service fetch;
    @service notifications;
    @tracked settings = {};
    @tracked stripeProductId;
    @tracked plans = [];
    @tracked paymentGateway;
    @tracked isLoading = false;

    @computed('isLoading', 'paymentGateway.code') get isStripeGateway() {
        return this.paymentGateway && this.paymentGateway.code === 'stripe';
    }

    constructor() {
        super(...arguments);
        this.load();
    }

    @action saveSettings() {
        if (this.isStripeGateway && !this.stripeProductId) {
            return this.notifications.warning('Stripe Product ID is required to setup billing with Stripe!');
        }

        this.isLoading = true;

        return this.fetch
            .post(
                'settings',
                {
                    stripeProductId: this.stripeProductId,
                },
                {
                    namespace: 'billing/int/v1',
                }
            )
            .then(() => {
                this.isDirty = false;
                this.notifications.success('Settings saved.');
            })
            .catch((error) => {
                this.notifications.serverError(error);
            })
            .finally(() => {
                this.isLoading = false;
            });
    }

    @action load() {
        this.isLoading = true;

        return this.fetch
            .get('settings', {}, { namespace: 'billing/int/v1' })
            .then(async (settings) => {
                this.settings = settings;
                this.stripeProductId = settings.stripeProductId;
                await this.loadPaymentGateway();
                await this.loadPlans();
            })
            .catch((error) => {
                this.notifications.serverError(error);
            })
            .finally(() => {
                this.isLoading = false;
            });
    }

    @action loadPaymentGateway() {
        return this.store.queryRecord('payment-gateway', { code: this.settings.paymentGateway, single: true }).then((paymentGateway) => {
            this.paymentGateway = paymentGateway;
        });
    }

    @action loadPlans() {
        return this.store.query('plan', { limit: -1 }).then((plans) => {
            this.plans = plans;
        });
    }
}
