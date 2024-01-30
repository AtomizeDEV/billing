<?php

namespace Fleetbase\Billing\Http\Controllers;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Billing\Models\Company;
use Fleetbase\Billing\Support\Utils;
use Illuminate\Http\Request;

class StripeController extends Controller
{
    /**
     * Get current organization billing overview for Stipe
     *
     * @return \Illuminate\Http\Response
     */
    public function overview()
    {
        $company = Company::where('uuid', session('company'))->first();

        try {
            $stripe = Utils::getStripeClient();
        } catch (\Stripe\Exception\InvalidArgumentException $e) {
            return response()->error('Billing is not yet configured.');
        }

        $productId = Utils::getStripeProductId();
        $data = [];

        $data['isSubscribed'] = $isSubscribed = $company->subscribed($productId);
        $data['isGracePeriod'] = $isGracePeriod = $isSubscribed ? $company->subscription($productId)->onGracePeriod() : false;

        $priceOptions = $stripe->prices->all(
            [
                'active' => true,
                'limit' => 16,
                'product' => $productId,
                [
                    'expand' => [
                        'data.tiers'
                    ]
                ]
            ]
        );

        $prices = [];

        foreach ($priceOptions->data as $price) {
            $_price = $price->toArray();
            $_price['isSubscribed'] = $isPriceSubscribed = $company->subscribedToPrice($price->id, $productId);

            if ($isGracePeriod && !$isPriceSubscribed) {
                $_price['isDisabled'] = true;
            }

            $prices[] = $_price;
        }

        $defaultPaymentMethod = null;
        $storedPaymentMethods = [];
        $paymentMethods = [];

        try {
            $defaultPaymentMethod = $company->defaultPaymentMethod();
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // do nothing
        }

        $stripeDefaultPaymentMethod = $defaultPaymentMethod ? $defaultPaymentMethod->asStripePaymentMethod() : false;

        try {
            $storedPaymentMethods = $company->paymentMethods();
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // do nothing
        }

        foreach ($storedPaymentMethods as $paymentMethod) {
            $stripePaymentMethod = $paymentMethod->asStripePaymentMethod();
            $card = $stripePaymentMethod->card->toArray();
            $card['id'] = $stripePaymentMethod->id;

            if ($stripeDefaultPaymentMethod) {
                $card['isDefault'] = $stripeDefaultPaymentMethod->id === $stripePaymentMethod->id;
            }

            $paymentMethods[] = $card;
        }

        $data['paymentMethods'] = $paymentMethods;
        $data['defaultPaymentMethod'] = $stripeDefaultPaymentMethod ? $stripeDefaultPaymentMethod->card : null;
        $data['isTrialing'] = $company->onTrial();
        $data['trialEndsAt'] = $company->trialEndsAt($productId);
        $data['isElgibleForTrial'] = is_null($company->trialEndsAt($productId));
        $data['priceOptions'] = $prices;

        return response()->json($data);
    }

    /**
     * Create subscription for selected plan on current session.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function createSubscription(Request $request)
    {
        $paymentMethodId = $request->input('paymentMethodId');
        $isExistingPaymentMethod = $request->input('isExistingPaymentMethod', false);
        $priceId = $request->input('priceId');
        $productId = Utils::getStripeProductId();
        $trialDuration = Utils::getTrialDuration();

        // get current company
        $company = Company::where('uuid', session('company'))->first();

        if (!$company) {
            return response()->error('No organization authenticated to create subscription.');
        }

        // make sure company is stripe customer
        if (!$company->hasStripeId()) {
            try {
                $this->createStripeCustomer($company);
            } catch (\Exception $e) {
                return response()->error($e->getMessage());
            } catch (\Laravel\Cashier\Exceptions\CustomerAlreadyCreated $e) {
                // customer already exists! continue...
            }
        }

        // save payment method to company
        if (!$isExistingPaymentMethod) {
            $company->addPaymentMethod($paymentMethodId);
        }

        // $trialDays = now - trial_ends_at
        $trialEndsAt = \Illuminate\Support\Carbon::parse($company->trialEndsAt($productId));
        $trialEndsAt = $trialEndsAt->isValid() ? $trialEndsAt : now()->addDays($trialDuration);
        $trialDays = now()->diffInDays($trialEndsAt, false);
        $trialDays = $trialDays > 0 ? $trialDays : 0;

        // if company has any other subscriptions cancel them and create a new one
        $activeSubscriptions = $company->subscriptions()->active()->get(); // getting all the active subscriptions 

        $activeSubscriptions->map(function ($subscription) {
            $subscription->cancel(); // cancelling each of the active subscription
        });

        // create subscription
        $subscription = $company->newSubscription($productId, $priceId);

        // if has trial days available add them
        if ($trialDays > 0) {
            $subscription = $subscription->trialDays($trialDays);
        }

        $subscription = $subscription->create($paymentMethodId, [], ['expand' => ['latest_invoice']]);
        $stripeSubscription = $subscription->asStripeSubscription(['latest_invoice']);

        // get the charge id
        $chargeId = null;
        if (isset($stripeSubscription->latest_invoice)) {
            $chargeId = $stripeSubscription->latest_invoice->charge;
        }

        // get the order amount
        $amountPaid = null;
        if (isset($stripeSubscription->latest_invoice)) {
            $amountPaid = $stripeSubscription->latest_invoice->amount_paid;
        }

        // subscribed!
        return response()->json([
            'status' => 'success',
            'customer_id' => $company->stripe_id,
            'charge_id' => $chargeId,
            'amount_paid' => $amountPaid
        ]);
    }

    /**
     * Cancel the organizations current subscription. Inform them of grade period.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function cancelSubscription(Request $request)
    {
        $company = Company::where('uuid', session('company'))->first();
        $productId = Utils::getStripeProductId();

        if (!$company) {
            return response()->error('No organization authenticated to cancel subscription.');
        }

        // cancel subscription
        $company->subscription($productId)->cancel();

        $data = ['status' => 'success'];
        $data['isGracePeriod'] = $company->subscription($productId)->onGracePeriod();

        return response()->json($data);
    }

    /**
     * Resume the organizations current subscription.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function resumeSubscription(Request $request)
    {
        $company = Company::where('uuid', session('company'))->first();
        $productId = Utils::getStripeProductId();

        if (!$company) {
            return response()->error('No organization authenticated to resume subscription.');
        }

        $isGracePeriod = $company->subscription($productId)->onGracePeriod();

        // resume subscription
        if ($isGracePeriod) {
            $company->subscription($productId)->resume();

            return response()->json([
                'status' => 'success'
            ]);
        }


        return response()->error('Subscription cannot be resumed.');
    }

    /**
     * Update the user trial period and continue.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function continueWithTrial()
    {
        // get current company
        $company = Company::where('uuid', session('company'))->first();
        $trialDuration = Utils::getTrialDuration();

        if (!$company) {
            return response()->json([
                'errors' => ['No user session to trial'],
            ], 400);
        }

        if (isset($company->trial_ends_at)) {
            return response()->json([
                'errors' => ['User has already started trial'],
            ], 400);
        }

        // update trial ends at
        $company->update(['trial_ends_at' => now()->addDays($trialDuration)]);

        // ok
        return response()->json([
            'status' => 'ok'
        ]);
    }

    /**
     * Swap the users subscription plan.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function swapSubscription(Request $request)
    {
        $priceId = $request->input('priceId');
        $productId = Utils::getStripeProductId();
        $company = Company::where('uuid', session('company'))->first();

        if (!$company) {
            return response()->error('No organization authenticated to create subscription.');
        }

        if (!$priceId) {
            return response()->error('No plan selected to swap to.');
        }

        try {
            $company->subscription($productId)->swap($priceId);
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            return response()->error('Unable to swap subscription, please contact support.');
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Check to see if company is subscribed to stanrdard subscription.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function checkSubscriptionStatus()
    {
        // get current company
        $company = Company::where('uuid', session('company'))->first();
        $productId = Utils::getStripeProductId();

        // check if company has standard subscription
        return response()->json([
            'is_subscribed' => $company->subscribed($productId),
            'is_trialing' => $company->onTrial(),
            'trial_expires_at' => $company->trialEndsAt($productId),
            // 'trial_expires_at' => $company->subscription($productId)->trial_ends_at ?? null,
        ]);
    }

    /**
     * Save a new payment method to the organization.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function savePaymentMethod(Request $request)
    {
        $paymentMethod = $request->input('paymentMethod');
        $isDefault = $request->input('isDefault');

        // get current company
        $company = Company::where('uuid', session('company'))->first();

        if (!$company) {
            return response()->error('No organization to save payment method to.');
        }

        // make sure company is stripe customer
        if (!$company->hasStripeId()) {
            try {
                $this->createStripeCustomer($company);
            } catch (\Exception $e) {
                return response()->error($e->getMessage());
            }
        }

        // save payment method to company
        try {
            $company->addPaymentMethod($paymentMethod);
        } catch (\Stripe\Exception\CardException $e) {
            return response()->error($e->getMessage());
        }

        if ($isDefault) {
            $company->updateDefaultPaymentMethod($paymentMethod);
        }

        return response()->json([
            'status' => 'success',
        ]);
    }

    /**
     * update the default payment method.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function makePaymentMethodDefault(Request $request)
    {
        $paymentMethod = $request->input('paymentMethod');

        // get current company
        $company = Company::where('uuid', session('company'))->first();

        if (!$company) {
            return response()->error('No organization to update payment method to.');
        }

        $company->updateDefaultPaymentMethod($paymentMethod);

        return response()->json([
            'status' => 'success',
        ]);
    }

    /**
     * Save a new payment method to the organization.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function removePaymentMethod(Request $request)
    {
        $paymentMethodId = $request->input('paymentMethod');
        $company = Company::where('uuid', session('company'))->first();

        if (!$company) {
            return response()->error('No organization to remove payment method from.');
        }

        $paymentMethod = $company->findPaymentMethod($paymentMethodId);

        if ($paymentMethod) {
            $paymentMethod->delete();
        }

        return response()->json([
            'status' => 'success',
        ]);
    }

    /**
     * Get list of prices from stripe
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getPriceOptions(Request $request)
    {
        $stripe = Utils::getStripeClient();
        $productId = Utils::getStripeProductId();

        $result = $stripe->prices->all([
            'active' => true,
            'limit' => 16,
            'product' => $productId,
            ['expand' => ['data.tiers']]
        ]);

        return response()->json($result->data);
    }

    /**
     * Get current orgz payment methods
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function getPaymentMethods(Request $request)
    {
        $company = Company::where('uuid', session('company'))->first();
        $data = [];

        $data['paymentMethods'] = $company->paymentMethods();
        $data['defaultPaymentMethod'] = $company->defaultPaymentMethod();

        return response()->json($data);
    }

    /**
     * Creates a Stripe customer is non is existing for the organization.
     * 
     * @param \Fleetbase\Models\Company $company;
     * @return bool
     */
    public function createStripeCustomer(Company $company)
    {
        // load owner of company
        $company->load('owner');

        // we need to create as a customer within stripe
        $owner = $company->owner;

        // use the first company user created as owner, also update as company owner
        if (!$owner) {
            $owner = $company->users()->first();
            
            if ($owner) {
                // update company
                $company->owner_uuid = $owner->uuid;
                $company->save();
            }
        }

        // if no email must skip and notify user to provide email address
        if (!$owner) {
            throw new \Exception('No owner found for organization: ' . $company->name);
        }

        // if no email must skip and notify user to provide email address
        if (!$owner->email) {
            throw new \Exception('No email address found for organization owner: ' . $company->name);
        }

        $customer = $company->createAsStripeCustomer([
            'email' => $owner->email,
            'description' => 'Customer for ' . $company->name
        ]);

        return $customer;
    }
}
