<?php

namespace Fleetbase\Billing\Http\Controllers;

use Fleetbase\Http\Controllers\FleetbaseController;

class BillingResourceController extends FleetbaseController
{
    /**
     * The package namespace used to resolve from.
     *
     * @var string
     */
    public string $namespace = '\\Fleetbase\\Billing';
}
