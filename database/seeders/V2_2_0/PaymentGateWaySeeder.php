<?php

namespace Database\Seeders\V2_2_0;

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
        $payment_gateways = array(
            array('id' => '31','slug' => 'money-out','code' => '245','type' => 'AUTOMATIC','name' => 'Flutterwave','title' => 'Flutterwave Withdraw Payment Gateway','alias' => 'flutterwave-money-out','account_name' => NULL,'account_number' => NULL,'image' => NULL,'credentials' => '[{"label":"Public key","placeholder":"Enter Public key","name":"public-key","value":"FLWPUBK_TEST-8c91f68d3221f80efdd1d7f9fa9fb2d4-X"},{"label":"Secret key","placeholder":"Enter Secret key","name":"secret-key","value":"FLWSECK_TEST-SANDBOXDEMOKEY-X"},{"label":"Encryption key","placeholder":"Enter Encryption key","name":"encryption-key","value":"FLWSECK_TESTa21364cf85ef"},{"label":"Callback Url","placeholder":"Enter Callback Url","name":"callback-url","value":"https:\\/\\/webhook.site\\/b3e505b0-fe02-430e-a538-22bbbce8ce0d"},{"label":"Base Url","placeholder":"Enter Base Url","name":"base-url","value":"https:\\/\\/api.flutterwave.com\\/v3"}]','supported_currencies' => '["AED", "ARS", "AUD", "CAD", "CHF", "CZK", "ETB", "EUR", "GBP", "GHS", "ILS", "INR", "JPY", "KES", "MAD", "MUR", "MYR", "NGN", "NOK", "NZD", "PEN", "PLN", "RUB", "RWF", "SAR", "SEK", "SGD", "SLL", "TZS", "UGX", "USD", "XAF", "XOF", "ZAR", "ZMK", "ZMW", "MWK"]','crypto' => '0','desc' => NULL,'input_fields' => NULL,'env' => 'SANDBOX','status' => '1','last_edit_by' => '1','created_at' => '2024-04-26 10:22:16','updated_at' => '2024-04-26 10:23:37')

        );
        PaymentGateway::insert($payment_gateways);

        $payment_gateway_currencies = array(
            array('id' => '167','payment_gateway_id' => '31','name' => 'Flutterwave NGN','alias' => 'flutterwave-ngn-money-out-automatic','currency_code' => 'NGN','currency_symbol' => '₦','image' => NULL,'min_limit' => '1.0000000000000000','max_limit' => '100000.0000000000000000','percent_charge' => '0.0000000000000000','fixed_charge' => '0.0000000000000000','rate' => '1306.0000000000000000','created_at' => '2024-04-26 10:23:37','updated_at' => '2024-04-26 10:23:37')
        );


        PaymentGatewayCurrency::insert($payment_gateway_currencies);
    }
}
