<?php

namespace Fleetbase\Billing\Http\Controllers;

use Fleetbase\Http\Controllers\Controller;
use Fleetbase\Billing\Models\PaymentGateway;
use Fleetbase\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Saves billing configuration.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveSettings(Request $request)
    {
        $paymentGatewayId = $request->input('paymentGateway');
        $stripeProductId = $request->input('stripeProductId');
        $trialDuration = $request->input('trialDuration');
        $trialEnabled = $request->input('trialEnabled');

        if ($paymentGatewayId) {
            $paymentGateway = PaymentGateway::where('uuid', $paymentGatewayId)->first();

            if ($paymentGateway) {
                Setting::configure('billing.payment-gateway', $paymentGateway->code);
            }
        }

        if ($stripeProductId) {
            Setting::configure('billing.stripe-product-id', $stripeProductId);
        }

        if ($trialEnabled) {
            Setting::configure('billing.trial-enabled', $trialEnabled);
        }

        if ($trialDuration) {
            Setting::configure('billing.trial-duration', $trialDuration);
        }

        return response()->json(
            [
                'status' => 'ok'
            ]
        );
    }

    /**
     * Gets current billing configuration.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSettings()
    {
        $paymentGateway = Setting::lookup('billing.payment-gateway');
        $stripeProductId = Setting::lookup('billing.stripe-product-id');
        $trialDuration = Setting::lookup('billing.trial-duration', 14);
        $trialEnabled = Setting::lookup('billing.trial-enabled', true);

        return response()->json(
            [
                'paymentGateway' => $paymentGateway,
                'stripeProductId' => $stripeProductId,
                'trialDuration' => $trialDuration,
                'trialEnabled' => $trialEnabled,
            ]
        );
    }
}
