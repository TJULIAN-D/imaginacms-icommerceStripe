<?php

return [
    'name' => 'Icommercestripe',
    'paymentName' => 'icommercestripe',


/*
   |--------------------------------------------------------------------------
   | Configuration to Field User
   |--------------------------------------------------------------------------
*/

   'fieldName' => 'payoutStripeConfig',
/*
   |--------------------------------------------------------------------------
   | Configurations to create account
   |--------------------------------------------------------------------------
*/
    
    /*
    *  https://stripe.com/docs/connect/account-capabilities
    */
    'capabilities' => [
        'transfers' => ['requested' => true]
    ],
    /*
    * https://stripe.com/docs/connect/service-agreement-types
    */
    'tos_acceptance' => [
        'service_agreement' => 'recipient'
    ]
    
];
