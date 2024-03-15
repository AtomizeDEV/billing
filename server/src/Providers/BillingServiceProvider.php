<?php

namespace Fleetbase\Billing\Providers;

use Fleetbase\Billing\Support\Utils;
use Fleetbase\Providers\CoreServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

if (!class_exists(CoreServiceProvider::class)) {
    throw new \Exception('Billing extension cannot be loaded without `fleetbase/core-api` installed!');
}

/**
 * Billing extension service provider.
 *
 * @package \Fleetbase\Billing\Providers
 */
class BillingServiceProvider extends CoreServiceProvider
{
    /**
     * The observers registered with the service provider.
     *
     * @var array
     */
    public $observers = [
        \Fleetbase\Models\Company::class => \Fleetbase\Billing\Observers\CompanyObserver::class,
        \Fleetbase\Billing\Models\Subscription::class => \Fleetbase\Billing\Observers\SubscriptionObserver::class,
    ];

    /**
     * Register any application services.
     *
     * Within the register method, you should only bind things into the 
     * service container. You should never attempt to register any event 
     * listeners, routes, or any other piece of functionality within the 
     * register method.
     *
     * More information on this can be found in the Laravel documentation:
     * https://laravel.com/docs/8.x/providers
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(CoreServiceProvider::class);
    }

    /**
     * Bootstrap any package services.
     *
     * @return void
     *
     * @throws \Exception If the `fleetbase/core-api` package is not installed.
     */
    public function boot()
    {
        $this->registerObservers();
        $this->registerExpansionsFrom(__DIR__ . '/../Expansions');
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../migrations');
        $this->setCashierSecret();
    }

    /**
     * Set the cashier secret.
     *
     * @return void
     */
    public function setCashierSecret()
    {
        try {
            // Try to make a simple DB call
            DB::connection()->getPdo();

            // Check if the `billing_payment_gateways` table exists
            if (!Schema::hasTable('billing_payment_gateways')) {
                return;
            }
        } catch (\Exception $e) {
            // Connection failed, or other error occurred
            return;
        }

        // set stripe api secret
        $stripeGateway = Utils::getStripeGateway();

        if ($stripeGateway) {
            Config::set('cashier.secret', $stripeGateway->api_secret);
        }
    }
}
