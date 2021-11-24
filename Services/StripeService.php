<?php

namespace Modules\Icommercestripe\Services;

class StripeService
{

	public function __construct(){

	}

  /**
  * Make Configuration
  * @param 
  * @return Array 
  */
	public function makeConfigurationToGenerateLink($paymentMethod,$order,$transaction){
        
    //All API requests expect amounts to be provided in a currency’s smallest unit
    $amount = $order->total * 100;
   
    //Comision
    $feeAmount = $paymentMethod->options->comisionAmount; 
    $applicationFeeAmount = $feeAmount * 100;

    // Meta Data
    $metaData = [
      "orderId" => $order->id,
      "transactionId" => $transaction->id
    ];


    //Testing Values OJOOOOOOOOOOOOOOOOOOOOOOOOOOOOOOO
    $amount = 10000;//100$
     
    // All Params
		$params = array(
      'customer_email' => $order->email,
			'payment_method_types' => ['card'],
			'line_items' => [[
        'name' => stripeGetOrderDescription($order),
        'amount' => $amount,
        'currency' => 'USD',//$order->currency_code,
        'quantity' => 1,
      ]],
      'payment_intent_data' => [
        'application_fee_amount' => $applicationFeeAmount,
          'transfer_data' => [
            'destination' => 'acct_1JxGAK2aQQK2OIaa', //Account Connected
        ],
        'description' => stripeGetOrderDescription($order),
        'metadata' => $metaData
      ],
      'mode' => 'payment',
      'success_url' => $order->url,
      'cancel_url' => url('/'),
      'metadata' => $metaData
		);

		return $params;

  }

  /**
  * Get Status to Order
  * @param 
  * @return Int 
  */
  public function getStatusDetail($event){

      \Log::info('Icommercestripe: getStatusDetail - Event Type: '.$event->type);

      // Check Event Type
      switch ($event->type) {

        case "checkout.session.completed":
          $newStatus = 13; //processed
        break;

        case "checkout.session.expired":
          $newStatus = 14; //expired
        break;

        default: // Fallida
          $newStatus = 7; //failed
        break;

      }
      $details['newStatus'] = $newStatus;

      // Check Values from Sesion
      $session = $event->data->object;
      //\Log::info('Icommercestripe: getStatusDetail - Session: '.$session);

      if($session->metadata){
        $details['orderId'] = $session->metadata->orderId;
        $details['transactionId'] = $session->metadata->orderId;
      }
      
      \Log::info('Icommercestripe: getStatusDetail - New Order Status: '.$newStatus);

      return $details; 

  }
    

}