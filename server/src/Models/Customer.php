<?php

namespace Fleetbase\Billing\Models;

use Fleetbase\Casts\Json;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Models\Model;

class Customer extends Model
{
    use HasUuid;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'billing_customers';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_uuid',
        'payment_gateway_id',
        'pm_type',
        'pm_last_four',
        'trial_ends_at',
        'options',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'trial_ends_at' => 'datetime',
        'options' => Json::class
    ];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
