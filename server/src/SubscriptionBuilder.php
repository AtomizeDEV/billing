<?php

namespace Fleetbase\Billing;

use Laravel\Cashier\SubscriptionBuilder as CashierSubscriptionBuilder;

class SubscriptionBuilder extends CashierSubscriptionBuilder
{
    /**
     * Create the Eloquent Subscription.
     *
     * @param  \Stripe\Subscription  $stripeSubscription
     * @return \Laravel\Cashier\Subscription
     */
    protected function createSubscription(\Stripe\Subscription $stripeSubscription)
    {
        if ($subscription = $this->owner->subscriptions()->where('payment_gateway_id', $stripeSubscription->id)->first()) {
            return $subscription;
        }

        /** @var \Stripe\SubscriptionItem $firstItem */
        $firstItem = $stripeSubscription->items->first();
        $isSinglePrice = $stripeSubscription->items->count() === 1;

        /** @var \Laravel\Cashier\Subscription $subscription */
        $subscription = $this->owner->subscriptions()->create([
            'name' => $this->name,
            'payment_gateway_id' => $stripeSubscription->id,
            'payment_gateway_status' => $stripeSubscription->status,
            'payment_gateway_price' => $isSinglePrice ? $firstItem->price->id : null,
            'quantity' => $isSinglePrice ? ($firstItem->quantity ?? null) : null,
            'trial_ends_at' => !$this->skipTrial ? $this->trialExpires : null,
            'ends_at' => null,
        ]);

        /** @var \Stripe\SubscriptionItem $item */
        foreach ($stripeSubscription->items as $item) {
            $subscription->items()->create([
                'payment_gateway_id' => $item->id,
                'payment_gateway_product' => $item->price->product,
                'payment_gateway_price' => $item->price->id,
                'quantity' => $item->quantity ?? null,
            ]);
        }

        return $subscription;
    }
}
