<?php

namespace Fleetbase\Billing\Models;

use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasApiModelBehavior;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\SubscriptionItem as CashierSubscriptionItem;

class SubscriptionItem extends CashierSubscriptionItem
{
    use HasUuid, HasApiModelBehavior, SoftDeletes;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'billing_subscription_items';

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
    protected $searchableColumns = [];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subscription_uuid',
        'payment_gateway_id', // -> stripe_id
        'payment_gateway_price', // -> stripe_price
        'payment_gateway_product', // -> stripe_product
        'quantity'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [];

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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Set the Stripe ID.
     *
     * @param string|null $id
     * @return void
     */
    public function setStripeIdAttribute(?string $is)
    {
        $this->attributes['payment_gateway_id'] = $is;
    }

    /**
     * Set the Stripe Product ID.
     *
     * @param string|null $product
     * @return void
     */
    public function setStripeProductAttribute(?string $product)
    {
        $this->attributes['payment_gateway_product'] = $product;
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
     * Get the Stripe Product ID.
     *
     * @return string|null
     */
    public function getStripeProductAttribute()
    {
        return $this->payment_gateway_product;
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
}
