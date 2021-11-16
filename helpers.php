<?php

/**
* Get Payment Method Configuration
* @return collection
*/

if (!function_exists('stripeGetConfiguration')) {

 	function stripeGetConfiguration(){

        $paymentName = config('asgard.icommercestripe.config.paymentName');
        $attribute = array('name' => $paymentName);
        $paymentMethod = app("Modules\Icommerce\Repositories\PaymentMethodRepository")->findByAttributes($attribute); 
        
        return $paymentMethod;
    }

}

/**
* Decript url to get data   
* @param  $eUrl
* @return array
*/
if (!function_exists('stripeGetOrderDescription')) {

    function stripeGetOrderDescription($order){

        $description = "Orden #{$order->id} - {$order->first_name} {$order->last_name}";
        
        return  $description;

    }
}