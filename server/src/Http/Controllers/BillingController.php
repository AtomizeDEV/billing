<?php

namespace Fleetbase\Billing\Http\Controllers;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Billing\Models\PaymentGateway;
use Fleetbase\Billing\Models\Company;
use Fleetbase\Billing\Support\Utils;
use Fleetbase\Support\Utils as SupportUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    /**
     * View all subscriptions by payment gateway.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscriptions(Request $request)
    {
        $paymentGatewayCode = $request->input('code', Utils::getGatewayCode());
        $paymentGateway = PaymentGateway::where('code', $paymentGatewayCode)->first();
        $billables = [];

        if ($paymentGateway) {
            $billables = Company::whereHas('billingCustomer')->with([
                'subscriptions' => function ($query) use ($paymentGateway) {
                    $query->where('payment_gateway_uuid', $paymentGateway->uuid);
                    $query->without(['owner']);
                }
            ])->get();

            if ($billables) {
                // map with expanded data from gateway
                $billables = $billables->map(
                    function ($billable) {
                        if ($billable->subscriptions) {
                            $billable->subscriptions = $billable->subscriptions->map(function ($subscription) use ($billable) {
                                $subscription->stripeSubscription = $stripeSubscription = $subscription->asStripeSubscription();
                                $amount = 0;

                                if (is_array($stripeSubscription->items->data)) {
                                    foreach ($stripeSubscription->items->data as $itemData) {
                                        $amount += data_get($itemData, 'price.unit_amount');
                                    }
                                }

                                $subscription->data = SupportUtils::createObject(
                                    [
                                        'id' => data_get($stripeSubscription, 'id'),
                                        'plan' => data_get($stripeSubscription, 'plan.nickname'),
                                        'plan_id' => data_get($stripeSubscription, 'plan.id'),
                                        'plan_product_id' => data_get($stripeSubscription, 'plan.product'),
                                        'status' => data_get($stripeSubscription, 'status'),
                                        'interval' => 'every ' . Str::plural(data_get($stripeSubscription, 'plan.interval'), data_get($stripeSubscription, 'plan.interval_count')),
                                        'currency' => strtoupper(data_get($stripeSubscription, 'plan.currency')),
                                        'created' => Carbon::parse(data_get($stripeSubscription, 'created'))->toDateTimeString(),
                                        'collection_method' => Str::humanize(data_get($stripeSubscription, 'collection_method')),
                                        'current_period_start' => Carbon::parse(data_get($stripeSubscription, 'current_period_start'))->toDateTimeString(),
                                        'current_period_end' => Carbon::parse(data_get($stripeSubscription, 'current_period_end'))->toDateTimeString(),
                                        'is_on_grace_period' => $subscription->onGracePeriod(),
                                        'is_on_trial' => $billable->onTrial(),
                                        'trial_ends_at' => $billable->trialEndsAt($subscription->name),
                                        'amount' => $amount,
                                    ]
                                );

                                // for ui
                                $subscription->expanded = false;

                                return $subscription;
                            });
                        }

                        return $billable;
                    }
                );
            }
        }

        return response()->json($billables);
    }
}
