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

    private $paymentMethod;
    
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

        // Payment Method Configuration
        $this->paymentMethod = stripeGetConfiguration();
        
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
     * Init data to checkout
     * @param Requests request
     * @param Requests orderId
     * @return route
     */
    public function init(Request $request){
      
        \Log::info('Icommercestripe: INIT - '.time());

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

        // Validation event
        $event = null;
        try {
            $event = \Stripe\Webhook::constructEvent(
                $request->getContent(), $request->header('stripe-signature'), $this->paymentMethod->options->signSecret
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
            \Log::info('Icommercestripe: Response - Event Type: '.$event->type);

            if($event->data->object->object=="checkout.session"){
                $this->orderProcess($event);
            }
            if($event->data->object->object=="charge"){
                $this->chargesProcess($event);
            }

        }
        
        
        \Log::info('Icommercestripe: Response - END ');

        return response()->json($response, $status ?? 200);
       
    }

    /**
     * Create Account with Link to Onboarding proccess
     * @param Requests request
     * @return url
    */
    public function connectCreateAccountLinkOnboarding(Request $request){
        
        \Log::info('Icommercestripe: Connect - Create Account Link Onboarding');

        $response['status'] = "success";

        try {

            $data = $request['attributes'] ?? [];//Get data

            // Check if user has a payoutStripeConfig
            $userConfig = $this->stripeService->findPayoutConfigUser();

            
            if(is_null($userConfig)){

                // Create Account
                $account = $this->stripeApi->createAccount($this->paymentMethod->options->secretKey,$data);

                $accountId = $account->id;

                $response["title"] = trans("icommercestripe::icommercestripes.messages.accountCreated");

                // Save infor in User Profile Field
                $fieldCreated = $this->stripeService->syncDataUserField(['accountId'=> $accountId]);
            }else{

                $response["title"] = trans("icommercestripe::icommercestripes.messages.accountAlreadyHave");

                // Get account Id from Field
                $accountId = $userConfig->value->accountId;
            }

            // Create Account Link
            $accountLink = $this->stripeApi->createLinkAccount($this->paymentMethod->options->secretKey,$accountId);

            //Response
            $response["description"] = trans("icommercestripe::icommercestripes.messages.verifyAccount").$accountLink;

            //Data
            $response['data'] = [
                'accountRegisterLink' => $accountLink
            ];

        } catch (\Exception $e) {
            \Log::error("Icommercestripe: Connect - Create Account Link Onboarding: ".$e->getMessage());
            $status = 500;
            $response = [
                'status' => "error",
                'title' => "Ha ocurrido un error",
                'description' => $e->getMessage()
            ];
        }

        return response()->json($response, $status ?? 200);

    }

    
    /**
     * Get Account Data and Create urlPanel (Login Link) if it is not saved in profile field
     * @param Requests request
     * @return url
    */
    public function connectGetAccount(Request $request){
        
        \Log::info('Icommercestripe: Connect - Get Account');

        try {

            $data = $request['attributes'] ?? [];//Get data

            // Get Account ID - Just for testing API
            if(isset($data['accountId'])){
                $accountId = $data['accountId'];
            }else{
                 // Check if user has a payoutStripeConfig from logged user
                $userConfig = $this->stripeService->findPayoutConfigUser();

                if(!is_null($userConfig)){
                    // Get account Id from Field
                    $accountId = $userConfig->value->accountId;

                }else{
                    throw new \Exception("User Payout Config - No Exist", 204);
                }
            }

            // Response Infor Account
            $accountInfor = $this->stripeApi->retrieveAccount($this->paymentMethod->options->secretKey,$accountId);

            // Check if exist urlPanel
            if(isset($userConfig) && isset($userConfig->value->urlPanel)){

                $responseTent['urlPanel'] = $userConfig->value->urlPanel;

            }else{

                // Validate if can create a login link
                if(stripeValidateAccountRequirements($accountInfor)){

                    // Create Login Link
                    $responseLoginLink = $this->stripeApi->createLoginLink($this->paymentMethod->options->secretKey,$accountId);
                    
                    // Save infor in User Profile Field
                    $fieldCreated = $this->stripeService->syncDataUserField(['urlPanel'=> $responseLoginLink->url]);

                    // Add to response
                    $responseTent['urlPanel'] = $responseLoginLink->url;

                }else{

                    $responseTent['urlPanelMsj'] = trans("icommercestripe::icommercestripes.validation.accountIncompletePanelUrl");
                }        
                
            }
            
            // Response
            $responseTent['email'] = $accountInfor->email;
            $responseTent['detailsSubmitted'] = $accountInfor->details_submitted;
            $responseTent['chargesEnabled'] = $accountInfor->charges_enabled;//Pagos
            $responseTent['payoutsEnabled'] = $accountInfor->payouts_enabled;//Transf

            $response['data'] = $responseTent;
            
                
        } catch (\Exception $e) {
            \Log::error("Icommercestripe: Connect - Get Account ".$e->getMessage());
            $status = $e->getCode();
            $response = [
                'errors' => $e->getMessage()
            ];
        }

        return response()->json($response, $status ?? 200);

    }

    /**
     * Create login link
     * @param Request accountId
     * @return url
    */
    public function connectCreateLoginLink(Request $request){
        
        \Log::info('Icommercestripe: Connect - Create Login Link');

        try {

            $data = $request['attributes'] ?? [];//Get data

            // Payment Method Configuration
            $paymentMethod = stripeGetConfiguration();

            $result = $this->stripeApi->createLoginLink($paymentMethod,$data['accountId']);

            $response = $result;

        } catch (\Exception $e) {
            \Log::error("Icommercestripe: Connect - Create Link: ".$e->getMessage());
            $status = 500;
            $response = [
                'errors' => $e->getMessage()
            ];
        }

        return response()->json($response, $status ?? 200);

    }


    /**
    * Just Testing response
    * @param Requests request
    * @return
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

    /**
     * 
     * @param Requests request
     * @return Countries Stripe
    */
    public function connectGetCountry(Request $request){
        
        \Log::info('Icommercestripe: Connect - Get Country');

        try {

            $data = $request['attributes'] ?? [];//Get data

            // Payment Method Configuration
            $paymentMethod = stripeGetConfiguration();

            $responseStripe = $this->stripeApi->retrieveCountry($paymentMethod->options->secretKey,$data['countryCode'] ?? false);

            if(!isset($data['countryCode']) && count($responseStripe['data'])>0){

                $response['data'] = [
                    'countries' => $responseStripe['data'],
                    'count' => count($responseStripe['data'])
                ];

            }else{
                $response = $responseStripe; 
            }

        } catch (\Exception $e) {
            \Log::error("Icommercestripe: Connect - Get Country ".$e->getMessage());
            $status = 500;
            $response = [
                'errors' => $e->getMessage()
            ];
        }

        return response()->json($response, $status ?? 200);

    }

    /**
     * Update Order and Transaction
     * @param $event - (stripe information webhook)
     * @return
    */
    public function orderProcess($event){

        \Log::info('Icommercestripe: Response - Order Process - INIT - '.time());
        
        // Get all infor about status    
        $details = $this->stripeService->getStatusDetail($event); 

        if(isset($details['orderId'])){

            \Log::info('Icommercestripe: Response - Updating Order: '.$details['orderId']);

                
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
                
        }
        \Log::info('Icommercestripe: Response - Order Process - END - '.time());

    }

    /**
    * Create transfer to each product
    * @param $event - (stripe information webhook)
    * @return
    */
    public function chargesProcess($event){

        // Get Charge Infor
        $charge = $event->data->object;
        \Log::info('Icommercestripe: Response - Charge Process - Id: '.$charge->id);
        \Log::info('Icommercestripe: Response - Charge Process - Transfer Group: '.$charge->transfer_group);

        //Get order id from transfer group
        $infor = stripeGetInforTransferGroup($charge->transfer_group);
        
        $order = $this->order->find($infor[1]);

        //$order = $this->order->find(4);// Testinnnnggggggggg

        /*
        * Create Transfer to each product
        */
        \Stripe\Stripe::setApiKey($this->paymentMethod->options->secretKey);

        $description = stripeGetOrderDescription($order);

        /*
        * Testing Group Collection
        */
        /*
        \Log::info('Icommercestripe: Response - Charge Process - Items Count: '.$order->orderItems->count());
        $grouped = $order->orderItems->groupBy('title');
        //\Log::info('Icommercestripe: Response - Charge Process - Grouped: '.$grouped);
        foreach ($grouped as $key => $items) {
            \Log::info('Icommercestripe: Response - Charge Process - Group Key: '.$key);
            foreach ($items as $key2 => $item) {
               \Log::info('Icommercestripe: Response - Charge Process - Item Price: '.$item->price);
            }
            
        }
        */

        // Currency code from Config
        $currencyAccount = $this->paymentMethod->options->currency;

        // Currency Value from Icommerce
        $currencyConvertionValue = stripeGetCurrencyValue($currencyAccount);

        foreach ($order->orderItems as $key => $item) {

            
            if(!empty($item->product->organization_id)){

                // Get account Id to destination transfer
                $extraParams = $this->stripeService->getAccountIdByOrganizationId($item->product->organization_id,true);

                $destination = $extraParams['accountId'];
                
                // Get the amount in the currency of the Main Account
                $totalItem = stripeGetItemConvertion($order->currency_code,$currencyAccount,$item,$currencyConvertionValue);
                \Log::info('Icommercestripe: Response - Total Item: '.$totalItem); 

                // Get Comision
                $comision = $this->stripeService->getComisionToDestination($extraParams['user'],$totalItem);
                $amountFinal = $totalItem - $comision;
                \Log::info('Icommercestripe: Response - Amount Final: '.$amountFinal); 
                
                //All API requests expect amounts to be provided in a currency’s smallest unit
                $amountInCents = $amountFinal * 100;

                try{
                   
                    $transfer = \Stripe\Transfer::create([
                          'amount' => $amountInCents,
                          'currency' => $currencyAccount,
                          'source_transaction' => $charge->id,
                          'destination' => $destination,
                          'transfer_group' => $charge->transfer_group,
                          'description' => $description.' - Transfer',
                          'metadata' => ['comision'=>$comision]
                    ]);
                   
                    \Log::info('Icommercestripe: Response - Created Transfer to: '.$destination); 

                    // Save Credit
                    /*
                    $dataToCredit = [
                        'amount' => $amountFinal,
                        'userId' => $extraParams['user']->id,
                        'description' => 'Transferencia id: '.$transfer->id.' para el accountId Stripe: '.$destination,
                        'status' => 2
                    ];
                    $credit = app("Modules\Icredit\Services\CreditService")->create($dataToCredit);
                    */

                } catch (Exception $e) {
                    \Log::error('Icommercestripe: Response - Transfer - Message: '.$e->getMessage());
                }

            }else{
                \Log::info('Icommercestripe: Response - NO EXIST ITEM PRODUCT ORGANIZATION ID');  
            }
            
        }

    }



   
}