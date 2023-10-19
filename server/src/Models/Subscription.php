<?php

namespace Fleetbase\Billing\Models;

use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasApiModelBehavior;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    use HasUuid, HasApiModelBehavior, SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'billing_subscriptions';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'uuid';

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var string
     */
    public $incrementing = false;

    /**
     * These attributes that can be queried
     *
     * @var array
     */
    protected $searchableColumns = ['name'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_uuid',
        'payment_gateway_uuid',
        'name',
        'payment_gateway_id', // -> stripe_id
        'payment_gateway_status', // -> stripe_status
        'payment_gateway_price', // -> stripe_product
        'quantity',
        'trial_ends_at',
        'ends_at'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'trial_ends_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * Dynamic attributes that are appended to object
     *
     * @var array
     */
    protected $appends = [];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Get the model related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        return $this->setConnection(config('fleetbase.connection.db'))->belongsTo(Company::class, 'company_uuid');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->setConnection(config('fleetbase.connection.db'))->belongsTo(Company::class);
    }

    /**
     * Get the subscription items related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items()
    {
        return $this->hasMany(SubscriptionItem::class, '');
    }

    /**
     * Set the Stripe Price ID.
     *
     * @param string|null $id
     * @return void
     */
    public function setStripeIdAttribute(?string $id)
    {
        $this->attributes['payment_gateway_id'] = $id;
    }

    /**
     * Set the Stripe Subscription Status.
     *
     * @param string|null $status
     * @return void
     */
    public function setStripeStatusAttribute(?string $status)
    {
        $this->attributes['payment_gateway_status'] = $status;
    }

    /**
     * Set the Stripe Price ID.
     *
     * @param string|null $price
     * @return void
     */
    public function setStripePriceAttribute(?string $price)
    {
        $this->attributes['payment_gateway_price'] = $price;
    }

    /**
     * Get the Stripe ID.
     *
     * @return string|null
     */
    public function getStripeIdAttribute()
    {
        return $this->payment_gateway_id;
    }

    /**
     * Get the Stripe Status.
     *
     * @return string|null
     */
    public function getStripeStatusAttribute()
    {
        return $this->payment_gateway_status;
    }

    /**
     * Get the Stripe Price ID.
     *
     * @return string|null
     */
    public function getStripePriceAttribute()
    {
        return $this->payment_gateway_price;
    }

    /**
     * Filter query by active.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeActive($query)
    {
        $query->where(function ($query) {
            $query->whereNull('ends_at')
                ->orWhere(function ($query) {
                    $query->onGracePeriod();
                });
        })->where('payment_gateway_status', '!=', \Stripe\Subscription::STATUS_INCOMPLETE)
            ->where('payment_gateway_status', '!=', \Stripe\Subscription::STATUS_INCOMPLETE_EXPIRED)
            ->where('payment_gateway_status', '!=', \Stripe\Subscription::STATUS_UNPAID);

        if (\Laravel\Cashier\Cashier::$deactivatePastDue) {
            $query->where('payment_gateway_status', '!=', \Stripe\Subscription::STATUS_PAST_DUE);
        }
    }

    /**
     * Filter query by past due.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopePastDue($query)
    {
        $query->where('payment_gateway_status', \Stripe\Subscription::STATUS_PAST_DUE);
    }

    /**
     * Filter query by incomplete.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeIncomplete($query)
    {
        $query->where('payment_gateway_status', \Stripe\Subscription::STATUS_INCOMPLETE);
    }

    /**
     * Swap the subscription to new Stripe prices.
     *
     * @param  string|array  $prices
     * @param  array  $options
     * @return $this
     *
     * @throws \Laravel\Cashier\Exceptions\SubscriptionUpdateFailure
     */
    public function swap($prices, array $options = [])
    {
        if (empty($prices = (array) $prices)) {
            throw new \InvalidArgumentException('Please provide at least one price when swapping.');
        }

        $this->guardAgainstIncomplete();

        $items = $this->mergeItemsThatShouldBeDeletedDuringSwap(
            $this->parseSwapPrices($prices)
        );

        $stripeSubscription = $this->owner->stripe()->subscriptions->update(
            $this->stripe_id,
            $this->getSwapOptions($items, $options)
        );

        /** @var \Stripe\SubscriptionItem $firstItem */
        $firstItem = $stripeSubscription->items->first();
        $isSinglePrice = $stripeSubscription->items->count() === 1;

        $this->fill([
            'payment_gateway_status' => $stripeSubscription->status,
            'payment_gateway_price' => $isSinglePrice ? $firstItem->price->id : null,
            'quantity' => $isSinglePrice ? ($firstItem->quantity ?? null) : null,
            'ends_at' => null,
        ])->save();

        foreach ($stripeSubscription->items as $item) {
            $this->items()->updateOrCreate([
                'payment_gateway_id' => $item->id,
            ], [
                'payment_gateway_product' => $item->price->product,
                'payment_gateway_price' => $item->price->id,
                'quantity' => $item->quantity ?? null,
            ]);
        }

        // Delete items that aren't attached to the subscription anymore...
        $this->items()->whereNotIn('payment_gateway_price', $items->pluck('price')->filter())->delete();

        $this->unsetRelation('items');

        if ($this->hasIncompletePayment()) {
            (new \Laravel\Cashier\Payment(
                $stripeSubscription->latest_invoice->payment_intent
            ))->validate();
        }

        return $this;
    }
}
