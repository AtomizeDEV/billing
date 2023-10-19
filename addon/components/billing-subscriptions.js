import Component from '@glimmer/component';
import { tracked } from '@glimmer/tracking';
import { inject as service } from '@ember/service';

export default class BillingSubscriptionsComponent extends Component {
    @service fetch;
    @service notifications;
    @tracked billables = [];
    @tracked isLoading = false;

    constructor() {
        super(...arguments);
        this.loadSubscriptions();
    }

    loadSubscriptions() {
        this.isLoading = true;

        this.fetch
            .get('subscriptions', {}, { namespace: 'billing/int/v1' })
            .then((billables) => {
                this.billables = billables;
            })
            .finally(() => {
                this.isLoading = false;
            });
    }
}
