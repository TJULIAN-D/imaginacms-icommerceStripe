<?php

namespace Modules\Icommercestripe\Services;

class CreditService
{

	private $stripeService;
	private $creditService;

	public function __construct(){
    	$this->stripeService = app("Modules\Icommercestripe\Services\StripeService");
    	$this->creditService = app("Modules\Icredit\Services\CreditService");
	}

	/*
	* Get data to create credit when order is created
	*/
	public function getData($event){
		
		\Log::info('Icommercestripe: CreditService|GetData');  

		// Data Init
		$data = [];
		$amount = 0;
		$order = $event->order;

		// Payment Method Configuration
        $paymentMethod = stripeGetConfiguration();
		// Currency Code from Config PaymentMethod
        $currencyAccount = $paymentMethod->options->currency;
        // Currency Value from Icommerce
        $currencyConvertionValue = stripeGetCurrencyValue($currencyAccount);

        \Log::info('Icommercestripe: CreditService|GetData|OrganizationId: '.$order->organization_id);

        if(!empty($order->organization_id)){

        	//Get account Id to destination transfer
            $accountInfor = $this->stripeService->getAccountIdByOrganizationId($order->organization_id,true);

            // Get the amount in the currency of the Main Account
            //$totalOrder = stripeGetAmountConvertion($order->currency_code,$currencyAccount,$order->total,$currencyConvertionValue);

            // Monto del Credito (En la moneda default del sitio)
            $totalOrder = $order->total;

            // Get Comision
            $comision = $this->stripeService->getComisionToDestination($accountInfor['user'],$totalOrder);

            //Amount to Transfer
            $data['amount'] = $totalOrder - $comision;
            $data['userId'] = $accountInfor['user']->id;
        }

		return $data;
	}

	/*
	* Create a Credit after transfer
	*/
	public function create($order,$accountInfor,$transfer,$orderParent){

		\Log::info('Icommercestripe: CreditService|CreateCredit'); 

		// Monto del Credito (En la moneda de la orden)
        $totalOrder = $order->total;
        
        // Get Comision
        $comision = $this->stripeService->getComisionToDestination($accountInfor['user'],$totalOrder);

        //Amount Credit
        $amountCredit = $totalOrder - $comision;

        $descriptionCredit = 'Transferencia Stripe: '.$transfer->id.' para el accountId Stripe: '.$accountInfor['accountId'];

        // Save Credit - Order Child
        $dataToCredit = [
            'amount' => -$amountCredit,
            'customerId' => $accountInfor['user']->id,
            'description' => $descriptionCredit,
            'status' => 2,
            'relatedId' => $order->id,
            'relatedType' => get_class($order)
        ];
        $credit = $this->creditService->create($dataToCredit);


        // Save Credit - Order Padre
        $dataToCredit = [
            'amount' => -$amountCredit,
            'description' => $descriptionCredit,
            'status' => 2,
            'relatedId' => $orderParent->id,
            'relatedType' => get_class($order)
         ];
        $credit = $this->creditService->create($dataToCredit);


	}


}