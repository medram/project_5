<?php

return [
    /*
     * Provider-owned billing stays disabled until an operator confirms that
     * tax and invoice policy is approved for the merchant account.
     */
    'tax_policy_acknowledged' => (bool) env('CASHIER_TAX_POLICY_ACKNOWLEDGED', false),
];
