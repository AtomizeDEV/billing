import Model, { attr } from '@ember-data/model';
import { computed } from '@ember/object';
import { format, formatDistanceToNow } from 'date-fns';

export default class PlanModel extends Model {
    /** @ids */
    @attr('string') payment_gateway_uuid;
    @attr('string') payment_gateway_plan_id;

    /** @attributes */
    @attr('string') name;
    @attr('string') description;
    @attr('number') price;
    @attr('boolean') recurring;
    @attr('number') interval;
    @attr('string') billing_period;
    @attr('number') trial_period_days;
    @attr('raw') options;

    /** @dates */
    @attr('date') created_at;
    @attr('date') updated_at;

    /** @computed */
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
