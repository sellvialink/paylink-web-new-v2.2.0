<?php

namespace Database\Seeders\V2_1_0;

use Illuminate\Database\Seeder;
use App\Models\Admin\PaymentGateway;
use App\Models\Admin\PaymentGatewayCurrency;

class PaymentGateWaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // PayPal
        $perfect_id = PaymentGateway::insertGetId(array('slug' => 'add-money','code' => '3000','type' => 'AUTOMATIC','name' => 'Perfect Money','title' => 'Perfect Money Gateway','alias' => 'perfect-money','account_name' => NULL,'account_number' => NULL,'image' => '823e1a0a-ec99-4490-a014-b50749a1755b.webp','credentials' => '[{"label":"USD Account","placeholder":"Enter USD Account","name":"usd-account","value":"U39903302"},{"label":"EUR Account","placeholder":"Enter EUR Account","name":"eur-account","value":"E39620511"},{"label":"Alternate Passphrase","placeholder":"Enter Alternate Passphrase","name":"alternate-passphrase","value":"t0d2nbK2ZA92fRTnIFsMTWsHT"}]','supported_currencies' => '["USD","EUR"]','crypto' => '0','desc' => NULL,'input_fields' => NULL,'env' => 'SANDBOX','status' => '1','last_edit_by' => '1','created_at' => '2024-01-24 08:14:45','updated_at' => '2024-01-24 08:15:41'));

        PaymentGateway::where('alias', 'razorpay')->update([
            'supported_currencies' => ["USD","EUR","GBP","SGD","AED","AUD","CAD","CNY","SEK","NZD","MXN","BDT","EGP","HKD","INR","LBP","LKR","MAD","MYR","NGN","NPR","PHP","PKR","QAR","SAR","UZS","GHS"],
            'credentials' =>  '[{"label":"Public Key","placeholder":"Enter Public Key","name":"public-key","value":"rzp_test_voV4gKUbSxoQez"},{"label":"Secret Key","placeholder":"Enter Secret Key","name":"secret-key","value":"cJltc1jy6evA4Vvh9lTO7SWr"}]',
        ]);

        PaymentGatewayCurrency::insert([
            array('payment_gateway_id' => $perfect_id,'name' => 'Perfect Money EUR','alias' => 'add-money-perfect-money-eur-automatic','currency_code' => 'EUR','currency_symbol' => '€','image' => NULL,'min_limit' => '0.0000000000000000','max_limit' => '5000.0000000000000000','percent_charge' => '0.0000000000000000','fixed_charge' => '0.0000000000000000','rate' => '1.5200000000000000','created_at' => '2024-01-24 08:16:57','updated_at' => '2024-01-24 08:16:57'),
            array('payment_gateway_id' => $perfect_id,'name' => 'Perfect Money USD','alias' => 'add-money-perfect-money-usd-automatic','currency_code' => 'USD','currency_symbol' => '$','image' => NULL,'min_limit' => '0.0000000000000000','max_limit' => '5000.0000000000000000','percent_charge' => '0.0000000000000000','fixed_charge' => '0.0000000000000000','rate' => '1.0000000000000000','created_at' => '2024-01-24 08:16:57','updated_at' => '2024-01-24 08:16:57')
        ]);
    }

}
