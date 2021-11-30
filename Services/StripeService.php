<?php

namespace Modules\Icommercestripe\Services;

class StripeService
{

  private $fieldRepository;

	public function __construct(){
    $this->fieldRepository = app("Modules\Iprofile\Repositories\FieldRepository");
	}

  
  /**
  * Make Configuration
  * @param 
  * @return Array 
  */
  public function makeConfigurationToGenerateLink($paymentMethod,$order,$transaction){
        
   
    // Meta Data
    $metaData = [
      "orderId" => $order->id,
      "transactionId" => $transaction->id
    ];

    // All Params
    $params = array(
      'customer_email' => 'wavutes@gmail.com', //$order->email,
      'payment_method_types' => ['card'],
      'line_items' => $this->getLineItems($order),
      'payment_intent_data' => [
        'transfer_group' => stripeGetTransferGroup($order->id,$transaction->id),
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
  * Make Configuration
  * @param 
  * @return Array 
  */
	public function makeConfigurationToGenerateLinkDestinationCharge($paymentMethod,$order,$transaction){
        
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

    // All Params
		$params = array(
      'customer_email' => "wavutes@gmail.com",
			'payment_method_types' => ['card'],
			'line_items' => [[
        'name' => stripeGetOrderDescription($order),
        'amount' => $amount,
        'currency' => $order->currency_code,
        'quantity' => 1,
      ]],
      'payment_intent_data' => [
        'application_fee_amount' => $applicationFeeAmount,
          'transfer_data' => [
            'destination' => 'acct_1JxG0B2aOfu6i4RG', //Account Connected
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


  /**
  * Get Line Items
  * @param 
  * @return Int 
  */
  public function getLineItems($order){

    $items = [];
    foreach ($order->orderItems as $key => $item) {

      // AMOUUUNTT TESTTTTTTT ===================================
      //All API requests expect amounts to be provided in a currency’s smallest unit
      // $amount = $item->price * 100;
      $amount = 50 * 100; // 50$

      $itemInfor['price_data'] = [
        'currency' => 'USD',//$order->currency_code,
        'product_data' => [
          'name' => $item->product->name,
          'metadata' => ['id'=>$item->product->id]
        ],
        'unit_amount' => $amount
      ];
      $itemInfor['quantity'] = $item->quantity;
      array_push($items, $itemInfor);
    }

    return $items;

  }


  /**
  * 
  * @param 
  * @return Int 
  */
  public function findPayoutConfigUser(){

    //Data
    $payoutConfigName = "payoutStripeConfig";
    $userId = \Auth::check()->id ?? 1; // Just Testing

    // Check field for this user and name field
    $model = $this->fieldRepository
    ->where('name','=',$payoutConfigName)
    ->where('user_id', '=', $userId)
    ->first();

    return $model;

  }
  /**
  * 
  * @param 
  * @return Int 
  */
  public function syncDataUserField($payoutConfigValue){

     //Data
    $payoutConfigName = "payoutStripeConfig";
    $userId = \Auth::check()->id ?? 1; // Just Testing

    // Data Field
    $dataField = [
      'user_id' => $userId, 
      'name' => $payoutConfigName,
      'value' => $payoutConfigValue
    ];

    $modelExist = $this->findPayoutConfigUser();

    // Create or Update
    if($modelExist)
      $result = $this->fieldRepository->update($modelExist,$dataField);
    else
      $result = $this->fieldRepository->create($dataField);
    

    return $result;

  }

    

}