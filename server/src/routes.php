<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix(config('billing.api.routing.prefix', 'billing'))->namespace('Fleetbase\Billing\Http\Controllers')->group(
    function ($router) {
        /*
        |--------------------------------------------------------------------------
        | Publicly Accessible Routes
        |--------------------------------------------------------------------------
        |
        | These are only for payment gateway webhooks.
        */
        $router->prefix('v1/receiver')->group(function ($router) {
            $router->any('stripe', 'CallbackController@stripe');
        });

        /*
        |--------------------------------------------------------------------------
        | Internal Billing API Routes
        |--------------------------------------------------------------------------
        |
        | Primary internal routes for console.
        */
        $router->prefix(config('billing.api.routing.internal_prefix', 'int'))->group(
            function ($router) {
                $router->group(
                    ['prefix' => 'v1', 'middleware' => ['fleetbase.protected']],
                    function ($router) {
                        // admin only routes
                        $router->group(['middleware' => [\Fleetbase\Billing\Http\Middleware\CheckIsAdminMiddleware::class]], function ($router) {
                            $router->fleetbaseRoutes('plans');
                            $router->fleetbaseRoutes('payment-gateways');
                            $router->group(
                                ['prefix' => 'settings'],
                                function ($router) {
                                    $router->post('/', 'SettingController@saveSettings');
                                }
                            );
                            $router->get('subscriptions', 'BillingController@subscriptions');
                        });

                        // end user routes
                        $router->group(
                            ['prefix' => 'settings'],
                            function ($router) {
                                $router->get('/', 'SettingController@getSettings');
                            }
                        );
                        // allow access to payment gateway infos
                        $router->group(
                            ['prefix' => 'payment-gateways'],
                            function ($router) {
                                $router->get('/', 'PaymentGatewayController@queryRecord');
                                $router->get('{uuid}', 'PaymentGatewayController@findRecord');
                            }
                        );
                        // stripe billing endpoints
                        $router->group(
                            ['prefix' => 'stripe'],
                            function ($router) {
                                $router->get('overview', 'StripeController@overview');
                                $router->put('swap-subscription', 'StripeController@swapSubscription');
                                $router->post('create-subscription', 'StripeController@createSubscription');
                                $router->post('resume-subscription', 'StripeController@resumeSubscription');
                                $router->post('save-payment-method', 'StripeController@savePaymentMethod');
                                $router->post('update-default-payment-method', 'StripeController@makePaymentMethodDefault');
                                $router->delete('cancel-subscription', 'StripeController@cancelSubscription');
                                $router->delete('remove-payment-method', 'StripeController@removePaymentMethod');
                            }
                        );
                    }
                );
            }
        );
    }
);
