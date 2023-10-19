import Component from '@glimmer/component';
import { inject as service } from '@ember/service';
import { computed } from '@ember/object';

export default class ModalsAddStripePaymentMethodComponent extends Component {
    @service theme;

    @computed('theme.currentTheme') get stripeElementOptions() {
        return {
            style: {
                base: {
                    color: this.theme.currentTheme === 'dark' ? '#ffffff' : '#252f3f',
                    lineHeight: '1.5',
                    iconColor: '#9fa6b2',
                    fontSize: '16px',
                    fontFamily: 'Inter, sans-serif',
                    fontWeight: '400',
                    fontSmoothing: 'antialiased',
                    '::placeholder': {
                        color: '#9fa6b2',
                    },
                },
                invalid: {
                    iconColor: '#F87171',
                    color: '#F87171',
                },
            },
        };
    }
}
