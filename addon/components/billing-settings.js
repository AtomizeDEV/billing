import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action } from '@ember/object';
import { later } from '@ember/runloop';

export default class BillingSettingsComponent extends Component {
    @service store;
    @service fetch;
    @service notifications;
    @service modalsManager;
    @tracked settings = {};
    @tracked trialDuration;
    @tracked trialEnabled = true;
    @tracked paymentGateways = [];
    @tracked paymentGateway;
    @tracked isLoading;
    @tracked isDirty = false;

    constructor() {
        super(...arguments);
        this.load();
    }

    @action setPaymentGateway(paymentGateway) {
        if (typeof paymentGateway === 'string') {
            paymentGateway = this.paymentGateways.find((gateway) => {
                return gateway.code === paymentGateway || gateway.id === paymentGateway;
            });
        }

        if (!paymentGateway) {
            return;
        }

        this.paymentGateway = paymentGateway;
        this.isDirty = true;
    }

    @action configurePaymentGateway() {
        if (!this.paymentGateway) {
            return;
        }

        return this.modalsManager.show('modals/configure-payment-gateway', {
            title: `Configure ${this.paymentGateway.name} payment gateway`,
            paymentGateway: this.paymentGateway,
            confirm: (modal) => {
                modal.startLoading();

                this.paymentGateway
                    .save()
                    .then((paymentGateway) => {
                        this.paymentGateway = paymentGateway;
                        this.notifications.success('Payment gateway configured.');
                        this.notifications.info('Page will now reload to take effect.');
                        modal.done();

                        // reload window
                        later(
                            this,
                            () => {
                                window.location.reload();
                            },
                            300 * 3
                        );
                    })
                    .catch((error) => {
                        this.notifications.serverError(error);
                    });
            },
        });
    }

    @action saveSettings() {
        if (!this.paymentGateway || !this.paymentGateway.id) {
            return this.notifications.warning('You must select a payment gateway.');
        }

        this.isLoading = true;

        return this.fetch
            .post(
                'settings',
                {
                    paymentGateway: this.paymentGateway.id,
                    trialDuration: this.trialDuration,
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
                this.trialDuration = settings.trialDuration;
                this.trialEnabled = settings.trialEnabled;
                await this.loadPaymentGateways();
            })
            .catch((error) => {
                this.notifications.serverError(error);
            })
            .finally(() => {
                this.isLoading = false;
                this.isDirty = false;
            });
    }

    loadPaymentGateways() {
        return this.store
            .findAll('payment-gateway')
            .then((paymentGateways) => {
                this.paymentGateways = paymentGateways;
                this.setPaymentGateway(this.settings.paymentGateway);
            })
            .catch((error) => {
                this.notifications.serverError(error);
            });
    }
}
