<?php

namespace Fleetbase\Billing\Seeds;

use Fleetbase\Models\Setting;
use Fleetbase\Billing\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class BillingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // create the stripe payment gateway
        $stripePaymentGateway = PaymentGateway::firstOrCreate(
            [
                'code' => 'stripe'
            ],
            [
                'name' => 'Stripe',
                'code' => 'stripe',
                'description' => 'Stripe\'s payments platform lets you accept credit cards, debit cards, and popular payment methods around the world—all with a single integration.',
                'callback_url' => url('billing/v1/receiver/stripe')
            ]
        );

        // set as default payment gateway
        Setting::configure('billing.payment-gateway', $stripePaymentGateway->code);
    }
}
