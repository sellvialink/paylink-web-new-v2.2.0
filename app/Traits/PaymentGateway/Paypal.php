<?php

namespace App\Traits\PaymentGateway;

use Exception;
use App\Traits\Transaction;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\Notification;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use App\Notifications\PaymentLink\Gateway\UserNotification;
use App\Notifications\PaymentLink\Gateway\BuyerNotification;


trait Paypal
{
    use Transaction;
    public function paypalInit($output = null) {
        if(!$output) $output = $this->output;
        $credentials = $this->getPaypalCredetials($output);

        $config = $this->paypalConfig($credentials,$output['charge_calculation']);
        $paypalProvider = new PayPalClient;
        $paypalProvider->setApiCredentials($config);
        $paypalProvider->getAccessToken();

        if($output['type'] == PaymentGatewayConst::TYPEPAYLINK){
            $return_url = route('payment-link.payment.success.paypal',PaymentGatewayConst::PAYPAL);
            $cancel_url = route('payment-link.payment.cancel.paypal',PaymentGatewayConst::PAYPAL);
        }elseif($output['type'] == PaymentGatewayConst::TYPEPRODUCT){
            $return_url = route('product-link.payment.success.paypal',PaymentGatewayConst::PAYPAL);
            $cancel_url = route('product-link.payment.cancel.paypal',PaymentGatewayConst::PAYPAL);
        }else{
            $return_url = route('invoice.payment.success.paypal',PaymentGatewayConst::PAYPAL);
            $cancel_url = route('invoice.payment.cancel.paypal',PaymentGatewayConst::PAYPAL);
        }

        $response = $paypalProvider->createOrder([
            "intent" => "CAPTURE",
            "application_context" => [
                "return_url" => $return_url,
                "cancel_url" => $cancel_url,
            ],
            "purchase_units" => [
                0 => [
                    "amount" => [
                        "currency_code" => $output['charge_calculation']['sender_cur_code'] ?? '',
                        "value" => $output['charge_calculation']['requested_amount'] ? number_format($output['charge_calculation']['requested_amount'],2,'.','') : 0,
                    ]
                ]
            ]
        ]);

        if(isset($response['id']) && $response['id'] != "" && isset($response['status']) && $response['status'] == "CREATED" && isset($response['links']) && is_array($response['links'])) {
            foreach($response['links'] as $item) {
                if($item['rel'] == "approve") {
                    $this->paypalJunkInsert($response);
                    return redirect()->away($item['href']);
                    break;
                }
            }
        }

        if(isset($response['error']) && is_array($response['error'])) {
            throw new Exception($response['error']['message']);
        }

        throw new Exception("Something went worng! Please try again.");
    }

    // Paypal Credential
    public function getPaypalCredetials($output) {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");
        $client_id_sample = ['api key','api_key','client id','primary key'];
        $client_secret_sample = ['client_secret','client secret','secret','secret key','secret id'];

        $client_id = '';
        $outer_break = false;
        foreach($client_id_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->paypalPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->paypalPlainText($label);

                if($label == $modify_item) {
                    $client_id = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }


        $secret_id = '';
        $outer_break = false;
        foreach($client_secret_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->paypalPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->paypalPlainText($label);

                if($label == $modify_item) {
                    $secret_id = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }

        return (object) [
            'client_id'     => $client_id,
            'client_secret' => $secret_id,
            'mode'          => "sandbox",
        ];

    }

    public function paypalPlainText($string) {
        $string = Str::lower($string);
        return preg_replace("/[^A-Za-z0-9]/","",$string);
    }


    public static function paypalConfig($credentials, $amount_info)
    {
        $config = [
            'mode'    => $credentials->mode ?? 'sandbox',
            'sandbox' => [
                'client_id'         => $credentials->client_id ?? "",
                'client_secret'     => $credentials->client_secret ?? "",
                'app_id'            => "APP-80W284485P519543T",
            ],
            'live' => [
                'client_id'         => $credentials->client_id ?? "",
                'client_secret'     => $credentials->client_secret ?? "",
                'app_id'            => "",
            ],
            'payment_action' => 'Sale', // Can only be 'Sale', 'Authorization' or 'Order'
            'currency'       => $amount_info['sender_cur_code'] ?? "",
            'notify_url'     => "", // Change this accordingly for your application.
            'locale'         => 'en_US', // force gateway language  i.e. it_IT, es_ES, en_US ... (for express checkout only)
            'validate_ssl'   => true, // Validate SSL when creating api client.
        ];
        return $config;
    }

    public function paypalJunkInsert($response) {

        $output = $this->output;
        $wallet_table = $output['wallet']->getTable();
        $wallet_id = $output['wallet']->id;

        $data = [
            'gateway'            => $output['gateway']->id,
            'currency'           => $output['currency']->id,
            'validated'          => $output['validated'],
            'charge_calculation' => json_decode(json_encode($output['charge_calculation']),true),
            'response'           => $response,
            'wallet_table'       => $wallet_table,
            'wallet_id'          => $wallet_id,
            'creator_guard'      => get_auth_guard(),
        ];

        return TemporaryData::create([
            'user_id'       => $output['wallet']->user_id,
            'type'          => PaymentGatewayConst::PAYPAL,
            'identifier'    => $response['id'],
            'data'          => $data,
        ]);
    }

    public function paypalSuccess($output = null) {
        if(!$output) $output = $this->output;
        $token = $this->output['tempData']['identifier'] ?? "";

        $credentials    = $this->getPaypalCredetials($output);
        $config         = $this->paypalConfig($credentials,$output['charge_calculation']);
        $paypalProvider = new PayPalClient;
        $paypalProvider->setApiCredentials($config);
        $paypalProvider->getAccessToken();
        $response = $paypalProvider->capturePaymentOrder($token);

        if(isset($response['status']) && $response['status'] == 'COMPLETED') {
            return $this->paypalPaymentCaptured($response,$output);
        }else {
            throw new Exception('Transaction failed. Payment captured failed.');
        }

        if(empty($token)) throw new Exception('Transaction failed. Record didn\'t saved properly. Please try again.');
    }

    public function paypalPaymentCaptured($response,$output) {
        // payment successfully captured record saved to database
        $output['capture'] = $response;
        try{
            $this->createTransaction($output);

        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }

        return true;
    }

    public function createTransaction($output) {

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

        $inserted_id = $this->insertRecord($output, $trx_id);
        $this->createTransactionChildRecords($inserted_id, $output);
        $this->removeTempData($output);


        $buyer = [
            'email' => $output['validated']['email'],
            'name'  => $output['validated']['full_name'],
        ];

        if($basic_setting->email_notification == true){
            $user = $output[$type]->user;
           try {
                $user->notify(new UserNotification($user, $output, $trx_id));
                Notification::route('mail', $buyer['email'])->notify(new BuyerNotification($buyer, $output, $trx_id));
           } catch (\Exception $e) {
            //Handle Error
           }
        }

    }

    public function insertRecord($output, $trx_id) {
        $trx_id =  $trx_id;

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
                'remark'                      => ucwords(remove_speacial_char($output['type']," ")) . " With " . $output['gateway']->name,
                'details'                     => json_encode($output),
                'status'                      => true,
                'payment_type'                => PaymentGatewayConst::TYPE_GATEWAY_PAYMENT,
                'attribute'                   => PaymentGatewayConst::RECEIVED,
                'created_at'                  => now(),
            ]);
            $this->updateWalletBalance($output);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }

    public function updateWalletBalance($output) {
        $update_amount = $output['receiver_wallet']->balance + $output['charge_calculation']['conversion_payable'];
        $output['receiver_wallet']->update([
            'balance'   => $update_amount,
        ]);
    }

    public function removeTempData($output) {
        $token = $output['capture']['id'];
        TemporaryData::where("identifier",$token)->delete();
    }
}
