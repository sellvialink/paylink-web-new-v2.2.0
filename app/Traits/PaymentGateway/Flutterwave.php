<?php
namespace App\Traits\PaymentGateway;

use Exception;
use App\Models\TemporaryData;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Http\Helpers\PaymentGateway;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use App\Constants\PaymentGatewayConst;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PaymentLink\Gateway\UserNotification;
use App\Notifications\PaymentLink\Gateway\BuyerNotification;

trait Flutterwave {

    private $flutterwave_gateway_credentials;
    private $request_credentials;
    private $flutterwave_api_base_url = "https://api.flutterwave.com/";
    private $flutterwave_api_v3       = "v3";

    public function flutterwaveInit($output = null) {
        if(!$output) $output = $this->output;
        $request_credentials = $this->getFlutterwaveRequestCredentials($output);

        return $this->createFlutterwavePaymentLink($output, $request_credentials);
    }


    public function registerFlutterwaveEndpoints($endpoint_key = null)
    {
        $endpoints = [
            'create-payment-link'       => $this->flutterwave_api_base_url . $this->flutterwave_api_v3 . "/payments",
        ];

        if($endpoint_key) {
            if(!array_key_exists($endpoint_key, $endpoints)) throw new Exception("Endpoint key [$endpoint_key] not registered! Register it in registerFlutterwaveEndpoints() method");

            return $endpoints[$endpoint_key];
        }

        return $endpoints;
    }

    public function createFlutterwavePaymentLink($output, $request_credentials) {

        $endpoint = $this->registerFlutterwaveEndpoints('create-payment-link');

        $temp_record_token = generate_unique_string('temporary_datas','identifier',60);
        $this->setUrlParams("token=" . $temp_record_token); // set Parameter to URL for identifying when return success/cancel

        if($output['type'] == PaymentGatewayConst::TYPEPAYLINK){
            $return_url = route('payment-link.payment.callback.flutterwave', $temp_record_token);
        }elseif($output['type'] == PaymentGatewayConst::TYPEPRODUCT){
            $return_url = route('product-link.payment.callback.flutterwave', $temp_record_token);
        }else{
            $return_url = route('invoice.payment.callback.flutterwave', $temp_record_token);
        }

        $temp_data = $this->flutterWaveJunkInsert($temp_record_token); // create temporary information

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $request_credentials->token,
        ])->post($endpoint,[
            'tx_ref'        => $temp_record_token,
            'amount'        => $output['charge_calculation']['requested_amount'],
            'currency'      => $output['charge_calculation']['sender_cur_code'],
            'redirect_url'  => $return_url,
            'customer'      => [
                'email' => $output['validated']['email'] ?? '',
                'name'  => $output['validated']['full_name'] ?? '',
            ],
            'customizations'    => [
                'title'     => "Add Money",
                'logo'      => get_fav(),
            ]
        ])->throw(function(Response $response, RequestException $exception) use ($temp_data) {
            $temp_data->delete();
            throw new Exception('An error occurred. Please contact support');
        })->json();

        $response_array = json_decode(json_encode($response), true);

        $temp_data_contains = json_decode(json_encode($temp_data->data),true);
        $temp_data_contains['response'] = $response_array;

        $temp_data->update([
            'data'  => $temp_data_contains,
        ]);

        return redirect()->away($response_array['data']['link']);
    }

    public function flutterWaveJunkInsert($temp_token) {
        $output = $this->output;
        $wallet_table = $output['wallet']->getTable();
        $wallet_id = $output['wallet']->id;

        $data = [
            'gateway'            => $output['gateway']->id,
            'currency'           => $output['currency']->id,
            'validated'          => $output['validated'],
            'charge_calculation' => json_decode(json_encode($output['charge_calculation']),true),
            'wallet_table'       => $wallet_table,
            'wallet_id'          => $wallet_id,
            'creator_guard'      => get_auth_guard(),
        ];

        return TemporaryData::create([
            'user_id'       => $output['wallet']->user_id,
            'type'          => PaymentGatewayConst::FLUTTERWAVE,
            'identifier'    => $temp_token,
            'data'          => $data,
        ]);
    }

    public function getFlutterwaveCredentials($output)
    {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");

        $public_key_sample = ['public key','test key','sandbox public key','public', 'test public','flutterwave public key', 'flutterwave public'];
        $secret_key_sample = ['secret','secret key','flutterwave secret','flutterwave secret key'];
        $encryption_key_sample    = ['encryption','encryption key','flutterwave encryption','flutterwave encryption key'];

        $public_key    = PaymentGateway::getValueFromGatewayCredentials($gateway,$public_key_sample);
        $secret_key         = PaymentGateway::getValueFromGatewayCredentials($gateway,$secret_key_sample);
        $encryption_key    = PaymentGateway::getValueFromGatewayCredentials($gateway,$encryption_key_sample);

        $mode = $gateway->env;
        $gateway_register_mode = [
            PaymentGatewayConst::ENV_SANDBOX => PaymentGatewayConst::ENV_SANDBOX,
            PaymentGatewayConst::ENV_PRODUCTION => PaymentGatewayConst::ENV_PRODUCTION,
        ];

        if(array_key_exists($mode,$gateway_register_mode)) {
            $mode = $gateway_register_mode[$mode];
        }else {
            $mode = PaymentGatewayConst::ENV_SANDBOX;
        }

        $credentials = (object) [
            'public_key'                => $public_key,
            'secret_key'                => $secret_key,
            'encryption_key'            => $encryption_key,
            'mode'                      => $mode
        ];

        $this->flutterwave_gateway_credentials = $credentials;

        return $credentials;
    }

    public function getFlutterwaveRequestCredentials($output = null)
    {
        if(!$this->flutterwave_gateway_credentials) $this->getFlutterwaveCredentials($output);
        $credentials = $this->flutterwave_gateway_credentials;
        if(!$output) $output = $this->output;

        $request_credentials = [];
        if($output['gateway']->env == PaymentGatewayConst::ENV_PRODUCTION) {
            $request_credentials['token']   = $credentials->secret_key;
        }else {
            $request_credentials['token']   = $credentials->secret_key;
        }

        $this->request_credentials = (object) $request_credentials;
        return (object) $request_credentials;
    }

    public function flutterwaveSuccess($output) {

        if(!$output) $output = $this->output;
        $token = $this->output['tempData']['identifier'] ?? "";
        if(empty($token)) throw new Exception('Transaction failed. Record didn\'t saved properly. Please try again.');

        $this->createTransactionFlutterwave($output);

    }

    public function createTransactionFlutterwave($output) {
        $basic_setting = BasicSettings::first();

        if($output['type'] == PaymentGatewayConst::TYPEPAYLINK){
            $type = 'payment_link';
            $trx_id = generateTrxString('transactions', 'trx_id', 'PL-', 8);
        }elseif($output['type'] == PaymentGatewayConst::TYPEPRODUCT){
            $type = 'product_link';
            $trx_id = generateTrxString('transactions', 'trx_id', 'PL-', 8);
        }else{
            $type = 'invoice';
            $trx_id = generateTrxString('transactions', 'trx_id', 'INV-', 8);
        }

        $trx_id =  $trx_id;
        $inserted_id = $this->insertRecordFlutterwave($output,$trx_id);
        $this->createTransactionChargeRecord($inserted_id, $output);
        $this->removeTempDataFlutterwave($output);

        $buyer = [
            'email' => $output['validated']['email'],
            'name'  => $output['validated']['full_name'],
        ];

        if($basic_setting->email_notification == true){
            $user = $output[$type]->user;
            $user->notify(new UserNotification($user, $output, $trx_id));
            Notification::route('mail', $buyer['email'])->notify(new BuyerNotification($buyer, $output, $trx_id));
        }
    }

    public function insertRecordFlutterwave($output, $trx_id) {

        $trx_id = $trx_id;

        if($output['type'] == PaymentGatewayConst::TYPEPAYLINK){
            $type = 'payment_link';
            $transaction_col = 'payment_link_id';
        }elseif($output['type'] == PaymentGatewayConst::TYPEPRODUCT){
            $type = 'product_link';
            $transaction_col = 'product_link_id';
        }else{
            $transaction_col = 'invoice_id';
            $type = 'invoice';
        }

        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
               'user_id'                     => $output['receiver_wallet']->user_id,
               'user_wallet_id'              => $output['receiver_wallet']->id,
               $transaction_col              => $output[$type]->id,
               'payment_gateway_currency_id' => $output['currency']->id,
               'type'                        => $output['type'],
               'trx_id'                      => $trx_id,
               'request_amount'              => $output['charge_calculation']['requested_amount'],
               'payable'                     => $output['charge_calculation']['payable'],
               'conversion_payable'          => $output['charge_calculation']['conversion_payable'],
               'request_amount_admin'        => $output['charge_calculation']['request_amount_admin'],
               'available_balance'           => $output['receiver_wallet']->balance + $output['charge_calculation']['conversion_payable'],
               'remark'                      => ucwords($output['type']." Transaction Successfully"),
               'details'                     => json_encode($output),
               'status'                      => true,
               'payment_type'                => PaymentGatewayConst::TYPE_GATEWAY_PAYMENT,
               'attribute'                   => PaymentGatewayConst::RECEIVED,
               'created_at'                  => now(),
            ]);

            $this->updateWalletBalanceFlutterwave($output);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }

    public function updateWalletBalanceFlutterwave($output) {
        $update_amount = $output['receiver_wallet']->balance + $output['charge_calculation']['conversion_payable'];
        $output['receiver_wallet']->update([
            'balance'   => $update_amount,
        ]);
    }

    public function removeTempDataFlutterwave($output) {
        $token = session()->get('identifier');
        TemporaryData::where("identifier",$token)->delete();
    }

}
