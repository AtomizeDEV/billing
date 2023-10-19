<?php

namespace Fleetbase\Billing\Support;

use Fleetbase\Billing\Models\PaymentGateway;
use Fleetbase\Models\Setting;

class Utils
{
    /**
     * Get Stripe PaymentGateway record.
     *
     * @return \Fleetbase\Billing\Models\PaymentGateway
     */
    public static function getStripeGateway(): ?\Fleetbase\Billing\Models\PaymentGateway
    {
        return PaymentGateway::where('code', 'stripe')->first();
    }

    /**
     * Get the StripeClient instance
     *
     * @return \Stripe\StripeClient
     */
    public static function getStripeClient(): ?\Stripe\StripeClient
    {
        $stripeGateway = static::getStripeGateway();
        $stripe = null;

        if ($stripeGateway) {
            /** @var \Stripe\StripeClient $stripe */
            $stripe = $stripeGateway->getClient();
        }

        return $stripe;
    }

    /**
     * Get the current Billing gateway.
     *
     * @return PaymentGateway|null
     */
    public static function getCurrentBillingGateway(): ?PaymentGateway
    {
        $code = static::getGatewayCode();
        return PaymentGateway::where('code', $code)->first();
    }

    /**
     * Get the current Billing gateway.
     *
     * @return string|null
     */
    public static function getGatewayCode(): ?string
    {
        return Setting::lookup('billing.payment-gateway');
    }

    /**
     * Get the current Stripe Product ID.
     *
     * @return string|null
     */
    public static function getStripeProductId(): ?string
    {
        return Setting::lookup('billing.stripe-product-id');
    }

    /**
     * Get the current Stripe Product ID.
     *
     * @return string|int
     */
    public static function getTrialDuration()
    {
        return Setting::lookup('billing.trial-duration', 14);
    }

    /**
     * Get the current Stripe Product ID.
     *
     * @return bool
     */
    public static function getTrialEnabled(): bool
    {
        return Setting::lookup('billing.trial-enabled', true);
    }
}
