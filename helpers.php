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
*   
* @param  
* @return
*/
if (!function_exists('stripeGetOrderDescription')) {

    function stripeGetOrderDescription($order){

        $description = "Orden #{$order->id} - {$order->first_name} {$order->last_name}";
        
        return  $description;

    }
}

/**
*    
* @param 
* @return
*/
if (!function_exists('stripeGetTransferGroup')) {

    function stripeGetTransferGroup($orderId,$transactionId){

        $description = "order-".$orderId."-transaction-".$transactionId;
        
        return  $description;

    }
}

/**
*    
* @param 
* @return
*/
if (!function_exists('stripeGetInforTransferGroup')) {

    function stripeGetInforTransferGroup($transferGroup){

        $infor = explode('-',$transferGroup);
        return  $infor;

    }
}

/**
*    
* @param 
* @return
*/
if (!function_exists('stripeValidateAccountRequirements')) {
    
    function stripeValidateAccountRequirements($stripeAccount){
        $validate = false;

        if($stripeAccount->details_submitted && $stripeAccount->charges_enabled && $stripeAccount->payouts_enabled)
            $validate = true;

        return $validate;
    }

}