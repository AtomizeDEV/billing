<?php

namespace Fleetbase\Billing\Observers;

class CompanyObserver
{
    /**
     * Handle the Company "created" event.
     *
     * @param  \Fleetbase\Models\Company  $company The Company that was created.
     * @return void
     */
    public function created(\Fleetbase\Models\Company $company): void
    {
        $billableCompanyInstance = \Fleetbase\Billing\Models\Company::where('uuid', $company->uuid)->first();

        if ($billableCompanyInstance) {
            // the current payment gateway code
            $paymentGatewayCode = \Fleetbase\Models\Setting::lookup('billing.payment-gateway');

            // if the payment gateway is stripe/ create as a stripe customer
            if ($paymentGatewayCode === 'stripe') {

                // load the stripe payment gateway
                $stripePaymentGateway = \Fleetbase\Billing\Support\Utils::getStripeGateway();

                // make sure stripe credentials is set on gateway
                if ($stripePaymentGateway && !empty($stripePaymentGateway->api_secret)) {
                    $billableCompanyInstance->load('owner');
                    $billableCompanyInstance->createAsStripeCustomer([
                        'email' => $billableCompanyInstance->owner->email,
                        'description' => 'Customer for ' . $billableCompanyInstance->name
                    ]);
                }
            }
        }
    }
}
