import Model, { attr } from '@ember-data/model';
import { computed } from '@ember/object';
import { format, formatDistanceToNow } from 'date-fns';

export default class PaymentGatewayModel extends Model {
    /** @attributes */
    @attr('string') name;
    @attr('string') code;
    @attr('string') description;
    @attr('string') api_key;
    @attr('string') api_secret;
    @attr('string') webhook_secret;
    @attr('string') callback_url;
    @attr('string') return_url;
    @attr('raw') options;

    /** @dates */
    @attr('date') created_at;
    @attr('date') updated_at;

    /** @computed */
    @computed('name', 'description') get selectName() {
        return this.name + ' - ' + this.description;
    }

    @computed('updated_at') get updatedAgo() {
        return formatDistanceToNow(this.updated_at);
    }

    @computed('updated_at') get updatedAt() {
        return format(this.updated_at, 'PPP p');
    }

    @computed('created_at') get createdAgo() {
        return formatDistanceToNow(this.created_at);
    }

    @computed('created_at') get createdAt() {
        return format(this.created_at, 'PPP p');
    }
}
