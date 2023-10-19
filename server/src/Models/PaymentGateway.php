<?php

namespace Fleetbase\Billing\Models;

use Fleetbase\Casts\Json;
use Fleetbase\Models\File;
use Fleetbase\Traits\HasUuid;
use Fleetbase\Traits\HasApiModelBehavior;
use Fleetbase\Models\Model;

class PaymentGateway extends Model
{
    use HasUuid, HasApiModelBehavior;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'billing_payment_gateways';

    /**
     * These attributes that can be queried
     *
     * @var array
     */
    protected $searchableColumns = ['name'];

    /**
     * The singularName overwrite.
     *
     * @var string
     */
    protected $singularName = 'paymentGateway';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'api_key',
        'api_secret',
        'webhook_secret',
        'return_url',
        'callback_url',
        'logo_uuid',
        'backdrop_uuid',
        'options'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'options' => Json::class
    ];

    /**
     * Dynamic attributes that are appended to object
     *
     * @var array
     */
    protected $appends = ['logo_url'];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'api_secret', 
        'webhook_secret'
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function logo()
    {
        return $this->setConnection(config('fleetbase.connection.db'))->belongsTo(File::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function backdrop()
    {
        return $this->setConnection(config('fleetbase.connection.db'))->belongsTo(File::class);
    }

    /**
     * @return string
     */
    public function getLogoUrlAttribute()
    {
        $default = $this->logo->url ?? null;
        $backup = 'https://flb-assets.s3.ap-southeast-1.amazonaws.com/static/image-file-icon.png';

        return $default ?? $backup;
    }

    /**
     * Returns an instance of the payment gateways client instance.
     *
     * @return \Stripe\StripeClien|null
     */
    public function getClient() {
        if ($this->code === 'stripe') {
            return new \Stripe\StripeClient($this->api_secret);
        }

        return null;
    }
}
