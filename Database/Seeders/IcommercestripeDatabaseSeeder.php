<?php

namespace Modules\Icommercestripe\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;
use Modules\Icommerce\Entities\PaymentMethod;

class IcommercestripeDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        
        Model::unguard();

        if(!is_module_enabled('Icommercestripe')){
            $this->command->alert("This module: Icommercestripe is DISABLED!! , please enable the module and then run the seed");
            exit();
        }
        
        //Validation if the module has been installed before
        $name = config('asgard.icommercestripe.config.paymentName');
        $result = PaymentMethod::where('name',$name)->first();

        if(!$result){

            $options['init'] = "Modules\Icommercestripe\Http\Controllers\Api\IcommerceStripeApiController";
            
            $options['publicKey'] = null;
            $options['secretKey'] = null;
            $options['accountId'] = null;
            $options['signSecret'] = null;
            $options['mode'] = "sandbox";
            $options['comisionAmount'] = 0;
            $options['minimunAmount'] = 4000;
            $options['maximumAmount'] = null;
      
            $titleTrans = 'icommercestripe::icommercestripes.single';
            $descriptionTrans = 'icommercestripe::icommercestripes.description';

            $params = array(
              'name' => $name,
              'status' => 1,
              'options' => $options
            );
            $paymentMethod = PaymentMethod::create($params);

            $this->addTranslation($paymentMethod,'en',$titleTrans,$descriptionTrans);
            $this->addTranslation($paymentMethod,'es',$titleTrans,$descriptionTrans);

        }else{

            $this->command->alert("This method has already been installed !!");

        }
   
    }


    /*
    * Add Translations
    * PD: New Alternative method due to problems with astronomic translatable
    **/
    public function addTranslation($paymentMethod,$locale,$title,$description){

      \DB::table('icommerce__payment_method_translations')->insert([
          'title' => trans($title,[],$locale),
          'description' => trans($description,[],$locale),
          'payment_method_id' => $paymentMethod->id,
          'locale' => $locale
      ]);
 
    }


}
