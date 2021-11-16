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

    $router->post('/connect/create-link', [
        'as' => 'icommercestripe.api.stripe.connectCreateLink',
        'uses' => 'IcommerceStripeApiController@connectCreateLink',
    ]);

    $router->get('/connect/account/get', [
        'as' => 'icommercestripe.api.stripe.connect.getAccount',
        'uses' => 'IcommerceStripeApiController@connectGetAccount',
    ]);

    $router->post('/connect/account-response', [
        'as' => 'icommercestripe.api.stripe.accountResponse',
        'uses' => 'IcommerceStripeApiController@connectAccountResponse',
    ]);

});