import { helper } from '@ember/component/helper';
import formatCurrency from '@atomizedev/ember-ui/utils/format-currency';

export default helper(function stripePriceAdditional([price, unit = 'order']) {
    const unitAmount = price.tiers[1].unit_amount;
    const priceCurrency = price.currency;
    const formattedPrice = formatCurrency(unitAmount, priceCurrency);

    return `${formattedPrice} per additional ${unit}`;
});
