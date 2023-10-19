import { helper } from '@ember/component/helper';
import { pluralize } from 'ember-inflector';

export default helper(function stripePriceIncluded([price, unit = 'order']) {
    const limit = price.tiers.firstObject.up_to;

    // hack for enterprise
    if (price.nickname.includes('Enterprise')) {
        return `Unlimited ${pluralize(unit)}`;
    }

    return `${pluralize(limit, unit)} included`;
});
