<?php

use Illuminate\Routing\Router;

$router->group(['prefix' => 'icommercestripe/v1'], function (Router $router) {
    
    $router->get('/', [
        'as' => 'icommercestripe.api.stripe.init',
        'uses' => 'IcommerceStripeApiController@init',
    ]);
    
    $router->post('/response', [
        'as' => 'icommercestripe.api.stripe.response',
        'uses' => 'IcommerceStripeApiController@response',
    ]);


    /*
    * Connect Routes
    */
    $router->group(['prefix' => 'payout/connect'], function (Router $router) {

        $router->post('/', [
            'as' => 'icommercestripe.api.stripe.connect.createAccountLinkOnboarding',
            'uses' => 'IcommerceStripeApiController@connectCreateAccountLinkOnboarding',
        ]);

        $router->get('/account/get', [
            'as' => 'icommercestripe.api.stripe.connect.getAccount',
            'uses' => 'IcommerceStripeApiController@connectGetAccount',
        ]);

        $router->post('/account-response', [
            'as' => 'icommercestripe.api.stripe.connect.accountResponse',
            'uses' => 'IcommerceStripeApiController@connectAccountResponse',
        ]);

        $router->post('/create-login-link', [
            'as' => 'icommercestripe.api.stripe.connect.CreateLoginLink',
            'uses' => 'IcommerceStripeApiController@connectCreateLoginLink',
        ]);

        $router->get('/country/get', [
            'as' => 'icommercestripe.api.stripe.connect.getCountry',
            'uses' => 'IcommerceStripeApiController@connectGetCountry',
        ]);

    });
   

});