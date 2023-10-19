<?php

namespace Fleetbase\Billing\Http\Controllers;

use Fleetbase\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CallbackController extends Controller
{
    /**
     * Handle Stripe webhook requests.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function stripe(Request $request)
    {
        $type = $request->input('type');
        $object = $request->input('data.object');

        // Handle the event
        // Review important events for Billing webhooks
        // https://stripe.com/docs/billing/webhooks
        switch ($type) {
            case 'invoice.paid':
                // The status of the invoice will show up as paid. Store the status in your
                // database to reference when a user accesses your service to avoid hitting rate
                // limits.
                \Illuminate\Support\Facades\Log::info('🔔  Webhook received! ' . print_r($object, true));
                break;
            case 'invoice.payment_failed':
                // If the payment fails or the customer does not have a valid payment method,
                // an invoice.payment_failed event is sent, the subscription becomes past_due.
                // Use this webhook to notify your user that their payment has
                // failed and to retrieve new card details.
                \Illuminate\Support\Facades\Log::info('🔔  Webhook received! ' . print_r($object, true));
                break;
            case 'customer.subscription.deleted':
                // handle subscription cancelled automatically based
                // upon your subscription settings. Or if the user
                // cancels it.
                \Illuminate\Support\Facades\Log::info('🔔  Webhook received! ' . print_r($object, true));
                break;
            case 'customer.subscription.updated':
                // handle subscription updated, sync to subscriptions new fields
                \Illuminate\Support\Facades\Log::info('🔔  Webhook received! ' . print_r($object, true));
                break;
                // ... handle other event types
            default:
                // Unhandled event type
        }

        return response()->json(['status' => 'success']);
    }
}
