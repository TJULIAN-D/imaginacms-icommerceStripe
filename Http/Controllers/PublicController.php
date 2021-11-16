<?php

namespace Modules\Icommercestripe\Http\Controllers;

// Requests & Response
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// Base
use Modules\Core\Http\Controllers\BasePublicController;

use Modules\Icommerce\Repositories\TransactionRepository;
use Modules\Icommerce\Repositories\OrderRepository;

// Services
use Modules\Icommercestripe\Services\StripeService;

class PublicController extends BasePublicController
{

    private $order;
    private $transaction;
    private $stripeService;

    public function __construct(
        OrderRepository $order,
        TransactionRepository $transaction,
        StripeService $stripeService
    )
    {
        $this->order = $order;
        $this->transaction = $transaction;
        $this->stripeService = $stripeService;
    }


    /**
     * Index data
     * @param Requests request
     * @return route
     */
    public function index($eURL){


        try {

            // Decr
            $infor = stripeDecriptUrl($eURL);
            $orderID = $infor[0];
            $transactionID = $infor[1];

            // Validate get data
            $order = $this->order->find($orderID);
            $transaction = $this->transaction->find($transactionID);
           
            // Get Payment Method Configuration
            $paymentMethod = stripeGetConfiguration();

            //View
            $tpl = 'icommercestripe::frontend.index';
          
            return view($tpl,compact('order','eURL'));


        } catch (\Exception $e) {

            \Log::error('Module Icommercestripe-Index: Message: '.$e->getMessage());
            \Log::error('Module Icommercestripe-Index: Code: '.$e->getCode());

            //Message Error
            $status = 500;
            $response = [
              'errors' => $e->getMessage(),
              'code' => $e->getCode()
            ];

            return redirect()->route("homepage");

        }
       

    }


}
