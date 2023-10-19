<?php

namespace Fleetbase\Billing\Observers;

use Fleetbase\Billing\Models\Subscription;
use Fleetbase\Billing\Support\Utils;

class SubscriptionObserver
{
    /**
     * Handle the Company "created" event.
     *
     * @param  \Fleetbase\Billing\Models\Subscriptiony $subscription The Subscription that was created.
     * @return void
     */
    public function created(Subscription $subscription): void
    {
        $paymentGateway = Utils::getCurrentBillingGateway();

        if ($paymentGateway) {
            $subscription->payment_gateway_uuid = $paymentGateway->uuid;
            $subscription->save();
        }
    }
}
