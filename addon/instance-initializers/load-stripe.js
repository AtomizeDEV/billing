import loadScript from '@adopted-ember-addons/ember-stripe-elements/utils/load-script';

export function initialize(owner) {
    loadScript('https://js.stripe.com/v3/').then(() => {
        // check if authenticated first
        const sessionService = owner.lookup('service:session');
        let isAuthenticated = sessionService.isAuthenticated;

        // If the session data is not yet available, check localStorage
        if (!isAuthenticated) {
            const localStorageSession = JSON.parse(window.localStorage.getItem('ember_simple_auth-session'));

            if (localStorageSession) {
                let { authenticated } = localStorageSession;
                let token = authenticated.token;

                // Check isAuthenticated again
                isAuthenticated = !!token;
            }
        }

        if (isAuthenticated === false) {
            return;
        }

        const stripeService = owner.lookup('service:stripev3');

        if (stripeService) {
            stripeService.loadStripeGatewayUsingFetch();
        }
    });
}

export default {
    initialize,
};
