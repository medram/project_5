<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['workspace_id', 'plan_id', 'provider', 'interval', 'reference', 'provider_id', 'approval_url'])]
class PaymentCheckoutAttempt extends Model {}
