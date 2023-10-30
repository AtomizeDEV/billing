import Engine from '@ember/engine';
import loadInitializers from 'ember-load-initializers';
import Resolver from 'ember-resolver';
import config from './config/environment';
import services from '@atomizedev/ember-core/exports/services';
import BillingSettingsComponent from './components/billing-settings';
import BillingPlansComponent from './components/billing-plans';
import BillingSubscriptionsComponent from './components/billing-subscriptions';
import BillingSubscriptionManagementComponent from './components/billing-subscription-management';

const { modulePrefix } = config;
const externalRoutes = ['console', 'extensions'];

export default class BillingEngine extends Engine {
    modulePrefix = modulePrefix;
    Resolver = Resolver;
    dependencies = {
        services,
        externalRoutes,
    };
    setupExtension = function (app, engine, universe) {
        // create link to user manages billing settings
        universe.registerOrganizationMenuItem('Billing Settings', {
            icon: 'credit-card',
            index: 3,
            slug: 'billing',
            component: BillingSubscriptionManagementComponent,
            onClick: (menuItem) => {
                // Provide the necessary context for the dynamic segments here
                const context = {
                    dynamicSegment1: 'value1',
                    dynamicSegment2: 'value2',
                };
                return universe.transitionMenuItem('console.settings.virtual', menuItem, context);
            },
        });

        // create the billing menu item in settings
        universe.registerSettingsMenuItem('Billing', {
            slug: 'billing',
            icon: 'credit-card',
            component: BillingSubscriptionManagementComponent,
        });

        // create billing navigation block in admin
        universe.registerAdminMenuPanel(
            'Billing Configuration',
            [
                {
                    title: 'Settings',
                    icon: 'cog',
                    component: BillingSettingsComponent,
                },
                {
                    title: 'Plans',
                    icon: 'money-check-dollar',
                    component: BillingPlansComponent,
                },
                {
                    title: 'Subscriptions',
                    icon: 'money-bill-wave',
                    component: BillingSubscriptionsComponent,
                },
            ],
            {
                slug: 'billing',
            }
        );
    };
}

loadInitializers(BillingEngine, modulePrefix);
