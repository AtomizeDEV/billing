import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';
import { action, computed, set, setProperties } from '@ember/object';
import { isArray } from '@ember/array';
import { not } from '@ember/object/computed';
import { intervalToDuration, formatDuration } from 'date-fns';

export default class BillingSubscriptionManagementComponent extends Component {
    @service store;
    @service fetch;
    @service currentUser;
    @service notifications;
    @service modalsManager;
    @service intl;
    @service hostRouter;
    @service router;
    @service('stripev3') stripe;
    @tracked settings = {};
    @tracked overview = {};
    @tracked paymentGateway;
    @tracked paymentMethods = [];
    @tracked selectedInterval = 'month';
    @tracked selectedPrice;
    @tracked isLoading;
    @tracked isLoadingOverview;
    @tracked isCancelling;
    @not('overview.isSubscribed') isNotSubscribed;
    @not('swapEnabled') swapDisabled;

    @computed('isLoading', 'price.id', 'priceOptions.[]', 'selectedPrice.id') get swapEnabled() {
        return this.priceOptions.find((price) => {
            const isSubscribed = price.isSubscribed;
            const isSelected = price.id === this.selectedPrice?.id;

            return !isSubscribed && isSelected;
        });
    }

    @computed('overview.trialEndsAt', 'isLoading') get trialDaysRemaining() {
        const { trialEndsAt } = this.overview;

        const duration = intervalToDuration({
            start: new Date(),
            end: new Date(trialEndsAt),
        });

        return formatDuration(duration, { format: ['years', 'months', 'days'] });
    }

    @computed('isLoading', 'overview.priceOptions', 'selectedInterval', 'this.overview.priceOptions.[]') get priceOptions() {
        if (!isArray(this.overview.priceOptions)) {
            return [];
        }

        // sort price options by cheapest tier first
        return this.overview.priceOptions
            .filter((price) => price.billing_scheme === 'tiered')
            .filter((price) => price.recurring.interval === this.selectedInterval)
            .sort((a, b) => {
                return a.tiers.firstObject.flat_amount - b.tiers.firstObject.flat_amount;
            });
    }

    constructor() {
        super(...arguments);
        this.loadOverview();
    }

    getRouter() {
        return this.hostRouter ?? this.router;
    }

    @action loadOverview() {
        this.isLoadingOverview = true;

        return this.fetch
            .get('settings', {}, { namespace: 'billing/int/v1' })
            .then(async (settings) => {
                this.settings = settings;
                await this.loadPaymentGateway();
                await this.loadBillingOverview();
            })
            .catch((error) => {
                this.notifications.serverError(error);
            })
            .finally(() => {
                this.isLoadingOverview = false;
            });
    }

    @action loadPaymentGateway() {
        return this.store.queryRecord('payment-gateway', { code: this.settings.paymentGateway, single: true }).then((paymentGateway) => {
            this.paymentGateway = paymentGateway;
        });
    }

    @action loadBillingOverview() {
        return this.fetch.get(`${this.paymentGateway.code}/overview`, {}, { namespace: 'billing/int/v1' }).then((overview) => {
            this.overview = overview;

            // set the selected price if any
            if (isArray(this.overview.priceOptions)) {
                this.selectedPrice = this.overview.priceOptions.find((price) => price.isSubscribed === true);
            }

            // set the users payment methods
            if (isArray(this.overview.paymentMethods)) {
                this.paymentMethods = this.overview.paymentMethods;
            }
        });
    }

    @action setPriceOption(priceOption) {
        this.selectedPrice = priceOption;
    }

    @action addPaymentMethod(options = {}) {
        this.modalsManager.show('modals/add-stripe-payment-method', {
            title: 'Add a new payment method',
            acceptButtonText: 'Save as new payment method',
            isFirstPaymentMethod: this.paymentMethods.length === 0,
            isDefault: this.paymentMethods.length === 0,
            isValid: false,
            setStripeElement: (stripeElement) => {
                this.modalsManager.setOption('stripeElement', stripeElement);
                this.modalsManager.setOption('acceptButtonDisabled', false);
                this.modalsManager.setOption('isValid', true);
            },
            validateInput: (event, stripeElement) => {
                if (stripeElement.error) {
                    this.modalsManager.setOption('isValid', false);
                    this.modalsManager.setOption('acceptButtonDisabled', true);
                } else {
                    this.modalsManager.setOption('acceptButtonDisabled', false);
                }
            },
            invalidateInput: () => {
                this.modalsManager.setOption('isValid', false);
                this.modalsManager.setOption('acceptButtonDisabled', true);
            },
            confirm: (modal) => {
                modal.startLoading();

                const stripeElement = modal.getOption('stripeElement');
                const isDefault = modal.getOption('isDefault');

                return this.stripe
                    .createPaymentMethod({ type: 'card', card: stripeElement })
                    .then((result) => {
                        if (result.error) {
                            this.isLoading = false;
                            this.notifications.error(result.error.message);
                            return;
                        }

                        // save server side
                        const { paymentMethod } = result;

                        return this.fetch
                            .post(`${this.paymentGateway.code}/save-payment-method`, { paymentMethod: paymentMethod.id, isDefault }, { namespace: 'billing/int/v1' })
                            .then(() => {
                                setProperties(paymentMethod, { isDefault, ...paymentMethod.card });

                                if (isDefault) {
                                    this.paymentMethods = this.paymentMethods.map((paymentMethod) => {
                                        set(paymentMethod, 'isDefault', false);
                                        return paymentMethod;
                                    });
                                }

                                this.paymentMethods.pushObject(paymentMethod);
                            });
                    })
                    .catch((error) => {
                        modal.stopLoading();
                        this.notifications.error(error.message);
                    });
            },
            ...options,
        });
    }

    @action selectPaymentMethod(options = {}) {
        this.modalsManager.show('modals/add-stripe-payment-method', {
            title: 'Select a payment method to use',
            acceptButtonText: 'Save as default payment method & continue',
            paymentMethods: this.paymentMethods,
            selectedPaymentMethod: null,
            selectPaymentMethod: (paymentMethod) => {
                this.modalsManager.setOption('selectedPaymentMethod', paymentMethod);
            },
            addPaymentMethod: () => {
                return this.addPaymentMethod({
                    onFinish: this.selectPaymentMethod,
                });
            },
            confirm: (modal) => {
                modal.startLoading();

                const selectedPaymentMethod = modal.getOption('selectedPaymentMethod');
                return this.makePaymentMethodDefault(selectedPaymentMethod);
            },
            ...options,
        });
    }

    @action makePaymentMethodDefault(paymentMethod) {
        set(paymentMethod, 'isLoading', true);

        return this.fetch
            .put(`${this.paymentGateway.code}/update-default-payment-method`, { paymentMethod: paymentMethod.id }, { namespace: 'billing/int/v1' })
            .then(() => {
                this.paymentMethods = this.paymentMethods.map((pm) => {
                    set(pm, 'isDefault', false);

                    if (pm.id === paymentMethod.id) {
                        set(pm, 'isDefault', true);
                    }

                    return pm;
                });
                set(paymentMethod, 'isLoading', false);
            })
            .catch((error) => {
                this.notifications.serverError(error);
                set(paymentMethod, 'isLoading', false);
            });
    }

    @action removePaymentMethod(paymentMethod, isDefault = false) {
        if (isDefault) {
            return this.notifications.warning('You need atleast one default payment method!');
        }

        this.modalsManager.confirm({
            title: 'Are you sure you wish to delete this payment method?',
            body: 'Proceeding will delete this payment method.',
            confirm: (modal) => {
                modal.startLoading();

                return this.fetch.delete(`${this.paymentGateway.code}/remove-payment-method`, { paymentMethod }, { namespace: 'billing/int/v1' }).then(() => {
                    // update payment methods
                    this.paymentMethods = this.paymentMethods.filter((pm) => {
                        return pm.id !== paymentMethod;
                    });
                });
            },
        });
    }

    @action createNewSubscription() {
        const price = this.selectedPrice;

        if (!price) {
            return this.notifications.warning('Make sure to select a plan to subscribe to.');
        }

        // if no payment methods prompt to create one
        if (!this.paymentMethods.length) {
            return this.addPaymentMethod({
                onConfirm: this.createNewSubscription,
            });
        }

        const paymentMethod = this.paymentMethods.find((paymentMethod) => paymentMethod.isDefault === true);

        // if no default payment method but payment methods available primpt to select
        if (!paymentMethod) {
            return this.selectPaymentMethod({
                onConfirm: this.createNewSubscription,
            });
        }

        // start loading & create new subscription using payment method
        this.isLoading = true;

        return this.fetch
            .post(
                `${this.paymentGateway.code}/create-subscription`,
                {
                    paymentMethodId: paymentMethod.id,
                    priceId: price.id,
                },
                { namespace: 'billing/int/v1' }
            )
            .then((response) => {
                if (response.status === 'success') {
                    this.notifications.success('Subscription started, services activated.');
                }

                this.reload();
                this.isLoading = false;
            })
            .catch((error) => {
                this.notifications.serverError(error);
                this.isLoading = false;
            });
    }

    @action updateSubscription() {
        const price = this.selectedPrice;

        this.modalsManager.confirm({
            title: `Are you sure you want to change your subscription to ${price.nickname}?`,
            body: `Proceeding will swap your subscription plan to ${price.nickname}, at the end of this billing cycle your subscription will be adjusted and you will be charged the prorated amount.`,
            acceptButtonText: `Yes, I want to change to ${price.nickname}`,
            acceptButtonScheme: 'primary',
            modalClass: 'modal-md',
            confirm: (modal) => {
                modal.startLoading();
                this.isLoading = true;

                return this.fetch
                    .put(`${this.paymentGateway.code}/swap-subscription`, { priceId: price.id }, { namespace: 'billing/int/v1' })
                    .then((response) => {
                        if (response.status === 'success') {
                            this.notifications.success('Your subscription has been updated.');
                        }

                        this.reload();
                        this.isLoading = false;
                    })
                    .catch((error) => {
                        this.notifications.serverError(error);
                        this.isLoading = false;
                    });
            },
        });
    }

    @action cancelSubscription() {
        const appName = this.intl.t('app.name');

        this.modalsManager.confirm({
            title: 'Are you sure you wish to cancel your subscription?',
            body: `Proceeding will cancel subscription, if you have days remaining you will still be able to access ${appName} services or resume your subscription during the grace period.`,
            modalClass: 'modal-md',
            acceptButtonScheme: 'danger',
            acceptButtonText: 'Cancel Subscription',
            confirm: (modal) => {
                modal.startLoading();
                this.isCanceling = true;

                return this.fetch
                    .delete(`${this.paymentGateway.code}/cancel-subscription`, {}, { namespace: 'billing/int/v1' })
                    .then((response) => {
                        if (response.status === 'success') {
                            this.notifications.success('Your subscription has been canceled.');
                        }

                        this.reload();
                        this.isCanceling = false;
                    })
                    .catch((error) => {
                        this.notifications.serverError(error);
                        this.isCanceling = false;
                    });
            },
        });
    }

    @action resumeSubscription() {
        this.modalsManager.confirm({
            title: 'Are you sure you wish to resume your subscription?',
            body: 'Proceeding will resume subscription, since you are within the grace period this means you will be billed at the end of the billing cycle and your subscription will resume as normal.',
            modalClass: 'modal-md',
            acceptButtonScheme: 'success',
            acceptButtonText: 'Resume Subscription',
            confirm: (modal) => {
                modal.startLoading();
                this.isLoading = true;

                return this.fetch
                    .post(`${this.paymentGateway.code}/resume-subscription`, {}, { namespace: 'billing/int/v1' })
                    .then((response) => {
                        if (response.status === 'success') {
                            this.notifications.success('Your subscription has been resumed!');
                        }

                        this.reload();
                        this.isLoading = false;
                    })
                    .catch((error) => {
                        this.notifications.serverError(error);
                        this.isLoading = false;
                    });
            },
        });
    }

    @action async reload() {
        await this.getRouter().refresh();
        return this.loadOverview();
    }
}
