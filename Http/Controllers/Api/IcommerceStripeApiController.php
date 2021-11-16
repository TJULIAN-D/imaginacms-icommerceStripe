<?php

namespace Modules\Icommercestripe\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

//Request
use Modules\Icommercestripe\Http\Requests\InitRequest;

// Base Api
use Modules\Icommerce\Http\Controllers\Api\OrderApiController;
use Modules\Icommerce\Http\Controllers\Api\TransactionApiController;
use Modules\Ihelpers\Http\Controllers\Api\BaseApiController;

// Repositories Icommerce
use Modules\Icommerce\Repositories\TransactionRepository;
use Modules\Icommerce\Repositories\OrderRepository;

use Modules\Icommercestripe\Repositories\IcommerceStripeRepository;

use Modules\Icommercestripe\Http\Controllers\Api\StripeApiController;

// Services
use Modules\Icommercestripe\Services\StripeService;


class IcommerceStripeApiController extends BaseApiController
{

    private $icommercestripe;
    private $order;
    private $orderController;
    private $transaction;
    private $transactionController;

    private $stripeApi;
    private $stripeService;
    
    public function __construct(
        IcommerceStripeRepository $icommercestripe,
        OrderRepository $order,
        OrderApiController $orderController,
        TransactionRepository $transaction,
        TransactionApiController $transactionController,
        StripeApiController $stripeApi,
        StripeService $stripeService
    ){
        $this->icommercestripe = $icommercestripe;

        $this->order = $order;
        $this->orderController = $orderController;
        $this->transaction = $transaction;
        $this->transactionController = $transactionController;

        $this->stripeApi = $stripeApi;
        $this->stripeService = $stripeService;
        
    }

    /**
    * Init Calculations (Validations to checkout)
    * @param Requests request
    * @return mixed
    */
    public function calculations(Request $request)
    {
      
      try {

        $paymentMethod = stripeGetConfiguration();
        $response = $this->icommercestripe->calculate($request->all(), $paymentMethod->options);
        
      } catch (\Exception $e) {
        //Message Error
        $status = 500;
        $response = [
          'errors' => $e->getMessage()
        ];
      }
      
      return response()->json($response, $status ?? 200);
    
    }

    

    /**
     * ROUTE - Init data
     * @param Requests request
     * @param Requests orderId
     * @return route
     */
    public function init(Request $request){
      
        try {
            
            
            $data = $request->all();
           
            $this->validateRequestApi(new InitRequest($data));

            $orderID = $request->orderId;
            //\Log::info('Module Icommercestripe: Init-ID:'.$orderID);

            // Payment Method Configuration
            $paymentMethod = stripeGetConfiguration();

            // Order
            $order = $this->order->find($orderID);
            $statusOrder = 1; // Processing

            // Validate minimum amount order
            if(isset($paymentMethod->options->minimunAmount) && $order->total<$paymentMethod->options->minimunAmount)
              throw new \Exception(trans("icommercestripe::icommercestripes.messages.minimum")." :".$paymentMethod->options->minimunAmount, 204);

            // Create Transaction
            $transaction = $this->validateResponseApi(
                $this->transactionController->create(new Request( ["attributes" => [
                    'order_id' => $order->id,
                    'payment_method_id' => $paymentMethod->id,
                    'amount' => $order->total,
                    'status' => $statusOrder
                ]]))
            );
            

            $responseStripeApi = $this->stripeApi->generateLink($paymentMethod,$order,$transaction);

            // Check success
            if(isset($responseStripeApi->url)){
                $redirectRoute = $responseStripeApi->url;
            }else{
                throw new \Exception('Icommercestripe - INIT - Generate Link URL', 500);
            }

            // Response
            $response = [ 'data' => [
                  "redirectRoute" => $redirectRoute,
                  "external" => true
            ]];


        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            $status = 500;
            $response = [
                'errors' => $e->getMessage()
            ];
        }


        return response()->json($response, $status ?? 200);

    }

     /**
     * Response
     * @param Requests request
     * @return route
     */
    public function response(Request $request){

        \Log::info('Icommercestripe: Response - INIT - '.time());

        // Default Response
        $response = ['status'=> 'error'];

        // Payment Method Configuration
        $paymentMethod = stripeGetConfiguration();

        // Validation event
        $event = null;
        try {
            $event = \Stripe\Webhook::constructEvent(
                $request->getContent(), $request->header('stripe-signature'), $paymentMethod->options->signSecret
            );
            $response = ['status'=> 'success'];
        } catch(\UnexpectedValueException $e) {
            $status = 400;
            \Log::error('Icommercestripe: Response - Error: Invalid Payload');
        } catch(\Stripe\Exception\SignatureVerificationException $e) {
            $status = 400;
            \Log::error('Icommercestripe: Response - Error: Signature'); 
        }

        // Check Event Type
        if(isset($event->type)){

            // Get all infor about status    
            $details = $this->stripeService->getStatusDetail($event);

            if(isset($details['orderId'])){

                \Log::info('Icommercestripe: Response - Updating Order: '.$details['orderId']);

                /*
                // Update Transaction
                $transaction = $this->validateResponseApi(
                    $this->transactionController->update($details['transactionId'],new Request([
                        'status' =>  $details['newStatus']
                    ]))
                );

                // Update Order Process
                $orderUP = $this->validateResponseApi(
                    $this->orderController->update($details['orderId'],new Request(
                        ["attributes" =>[
                            'status_id' => $details['newStatus']
                        ]
                    ]))
                );
                */
            }
           
        }
        
        
        \Log::info('Icommercestripe: Response - END');

        return response()->json($response, $status ?? 200);
       
    }

    /**
     * 
     * @param Requests request
     * @return url
    */
    public function connectCreateLink(Request $request){
        
        \Log::info('Icommercestripe: Connect - Create Link');

        try {

            $data = $request['attributes'] ?? [];//Get data

            // Payment Method Configuration
            $paymentMethod = stripeGetConfiguration();

            $response['url'] = $this->stripeApi->createLinkAccount($paymentMethod,$data);

        } catch (\Exception $e) {
            \Log::error("Icommercestripe: Connect - Create Link ".$e->getMessage());
            $status = 500;
            $response = [
                'errors' => $e->getMessage()
            ];
        }

        return response()->json($response, $status ?? 200);

    }

    
    /**
     * 
     * @param Requests request
     * @return url
    */
    public function connectGetAccount(Request $request){
        
        \Log::info('Icommercestripe: Connect - Get Account');

        try {

            $data = $request['attributes'] ?? [];//Get data

            // Payment Method Configuration
            $paymentMethod = stripeGetConfiguration();

            $responseStripe = $this->stripeApi->retrieveAccount($paymentMethod,$data['accountId']);
            
            $response = $responseStripe;

            // Response
            /*
            $response['data'] = [
                'email' => $responseStripe->email,
                'chargesEnabled' => $responseStripe->charges_enabled,// Pagos
                'payoutsEnabled' => $responseStripe->payouts_enabled // Transferencias
            ];
            */
            

        } catch (\Exception $e) {
            \Log::error("Icommercestripe: Connect - Get Account ".$e->getMessage());
            $status = 500;
            $response = [
                'errors' => $e->getMessage()
            ];
        }

        return response()->json($response, $status ?? 200);

    }

    /**
    * Response
    * @param Requests request
    * @return route
    */
    public function connectAccountResponse(Request $request){

        \Log::info('Icommercestripe: Response - Account - '.time());

        // Default Response
        $response = ['status'=> 'error'];


        // Handle the event
        /*
        switch ($event->type) {
          case 'account.updated':
            $account = $event->data->object;
          case 'account.application.authorized':
            $application = $event->data->object;
          case 'account.application.deauthorized':
            $application = $event->data->object;
          case 'account.external_account.created':
            $externalAccount = $event->data->object;
          case 'account.external_account.deleted':
            $externalAccount = $event->data->object;
          case 'account.external_account.updated':
            $externalAccount = $event->data->object;
          // ... handle other event types
          default:
            echo 'Received unknown event type ' . $event->type;
        }
        */

        return response()->json($response, $status ?? 200);

    }
   
}