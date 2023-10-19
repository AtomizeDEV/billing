<?php

namespace Fleetbase\Billing\Models;

use Fleetbase\Casts\Json;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasApiModelBehavior;
use Fleetbase\Models\Model;

class Plan extends Model
{
    use HasUuid, HasApiModelBehavior;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'billing_plans';

     /**
     * The singularName overwrite.
     *
     * @var string
     */
    protected $singularName = 'plan';

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
        'payment_gateway_uuid', 
        'name',
        'description',
        'payment_gateway_plan_id',
        'price',
        'recurring',
        'interval',
        'billing_period',
        'trial_period_days',
        'options'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'options' => Json::class,
        'recurring' => 'boolean'
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
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function paymentGateway()
    {
        return $this->belongsTo(PaymentGateway::class);
    }
}
