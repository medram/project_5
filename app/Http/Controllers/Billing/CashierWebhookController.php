<?php

namespace App\Http\Controllers\Billing;

use App\Services\Settings\PlatformSettings;
use Laravel\Cashier\Http\Controllers\WebhookController;

class CashierWebhookController extends WebhookController
{
    public function __construct(PlatformSettings $settings)
    {
        $settings->applyRuntime();

        parent::__construct();
    }
}
