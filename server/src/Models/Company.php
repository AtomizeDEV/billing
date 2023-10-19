<?php

namespace Fleetbase\Billing\Models;

use Fleetbase\Billing\Support\Utils;
use Fleetbase\Models\Company as Model;
use Laravel\Cashier\Billable;

class Company extends Model
{
    use Billable;

    /**
     * Begin creating a new subscription.
     *
     * @param  string  $name
     * @param  string|string[]  $prices
     * @return \Fleetbase\Billing\SubscriptionBuilder
     */
    public function newSubscription($name, $prices = [])
    {
        return new \Fleetbase\Billing\SubscriptionBuilder($this, $name, $prices);
    }

    /**
     * Get all of the subscriptions for the Stripe model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, $this->getForeignKey())->orderBy('created_at', 'desc');
    }

    /**
     * Get all of the subscriptions for the Stripe model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function billingCustomer()
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * The stripe ID for the company.
     *
     * @return string|null
     */
    public function getStripeIdAttribute()
    {
        if (!$this->relationLoaded('billingCustomer')) {
            $this->load('billingCustomer');
        }

        if ($this->billingCustomer) {
            return $this->billingCustomer->payment_gateway_id;
        }

        return null;
    }

    /**
     * The billable company trial ends at.
     *
     * @return string|null
     */
    public function getTrialEndsAtAttribute()
    {
        if (!$this->relationLoaded('billingCustomer')) {
            $this->load('billingCustomer');
        }

        if ($this->billingCustomer) {
            return $this->billingCustomer->trial_ends_at;
        }

        return null;
    }

    /**
     * The billable company payment method type.
     *
     * @return string|null
     */
    public function getPmTypeAttribute()
    {
        if (!$this->relationLoaded('billingCustomer')) {
            $this->load('billingCustomer');
        }

        if ($this->billingCustomer) {
            return $this->billingCustomer->pm_type;
        }

        return null;
    }

    /**
     * The billable company payment method last four.
     *
     * @return string|null
     */
    public function getPmLastFourAttribute()
    {
        if (!$this->relationLoaded('billingCustomer')) {
            $this->load('billingCustomer');
        }

        if ($this->billingCustomer) {
            return $this->billingCustomer->pm_last_four;
        }

        return null;
    }

    /**
     * Sets attributes to the billing customer record.
     *
     * @param array $attributes
     * @return void
     */
    public function setBillingCustomerAttributes(array $attributes)
    {
        if (!$this->relationLoaded('billingCustomer')) {
            $this->load('billingCustomer');
        }

        if ($this->billingCustomer) {
            $this->billingCustomer->update($attributes);
        } else {
            // set the trial_ends_at
            $trialEnabled = Utils::getTrialEnabled();
            if ($trialEnabled) {
                $attributes['trial_ends_at'] = \Illuminate\Support\Carbon::now()->addDays(Utils::getTrialDuration());
            }

            $billingCustomer = $this->billingCustomer()->create($attributes);
            $this->setRelation('billingCustomer', $billingCustomer);
        }
    }

    /**
     * Set the Stripe ID to the customer.
     * 
     * @param string $id
     * @void
     */
    public function setStripeIdAttribute(?string $id)
    {
        $this->setBillingCustomerAttributes(['payment_gateway_id' => $id]);
    }

    /**
     * Set the Customer payment method type.
     * 
     * @param string $type
     * @void
     */
    public function setPmTypeAttribute(?string $type)
    {
        $this->setBillingCustomerAttributes(['pm_type' => $type]);
    }

    /**
     * Set the Customer payment method last four.
     * 
     * @param string $lastFour
     * @void
     */
    public function setPmLastFourAttribute(?string $lastFour)
    {
        $this->setBillingCustomerAttributes(['pm_last_four' => $lastFour]);
    }

    /**
     * Set the date/timestamp the customer's trial ends at.
     * 
     * @param string|interger|DateTime $trialEndsAt
     * @void
     */
    public function setTrialEndsAtAttribute($trialEndsAt)
    {
        $this->setBillingCustomerAttributes(['trial_ends_at' => $trialEndsAt]);
    }
}
