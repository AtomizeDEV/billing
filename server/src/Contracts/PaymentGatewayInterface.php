<?php

namespace Fleetbase\Billing\Contracts;

use Fleetbase\Models\User;
use Fleetbase\Billing\Models\Plan;
use Fleetbase\Billing\Models\Subscription;

interface PaymentGatewayInterface
{
    /**
     * Create a subscription for a user.
     *
     * @param User $user
     * @param Plan $plan
     * @return Subscription
     */
    public function createSubscription(User $user, Plan $plan): Subscription;

    /**
     * Cancel a subscription.
     *
     * @param Subscription $subscription
     * @return void
     */
    public function cancelSubscription(Subscription $subscription): void;

    // ... other methods as necessary ...
}
