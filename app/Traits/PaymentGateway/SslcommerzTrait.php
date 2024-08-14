<?php

namespace App\Traits\PaymentGateway;

use Exception;
use Carbon\Carbon;
use App\Traits\Transaction;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PaymentLink\Gateway\UserNotification;
use App\Notifications\PaymentLink\Gateway\BuyerNotification;

trait SslcommerzTrait
{

    use Transaction;

    public function sslcommerzInit($output = null) {
        if(!$output) $output = $this->output;
        $credentials = $this->getSslCredentials($output);
        $reference = generateTransactionReference();
        $amount = $output['charge_calculation']['requested_amount'] ? number_format($output['charge_calculation']['requested_amount'],2,'.','') : 0;
        $currency = $output['charge_calculation']['sender_cur_code'];

        if($output['type'] == PaymentGatewayConst::TYPEPAYLINK){
            $success_url = route('payment-link.payment.success.ssl');
            $cancel_url  = route('payment-link.payment.cancel.ssl');
            $fail_url    = route('payment-link.payment.fail.ssl');
        }elseif($output['type'] == PaymentGatewayConst::TYPEPRODUCT){
            $success_url = route('product-link.payment.success.ssl');
            $cancel_url  = route('product-link.payment.cancel.ssl');
            $fail_url    = route('product-link.payment.fail.ssl');
        }else{
            $success_url = route('invoice.payment.success.ssl');
            $cancel_url  = route('invoice.payment.cancel.ssl');
            $fail_url    = route('invoice.payment.fail.ssl');
        }

        $post_data = array();
        $post_data['store_id']     = $credentials->store_id??"";
        $post_data['store_passwd'] = $credentials->store_password??"";
        $post_data['total_amount'] = $amount;
        $post_data['currency']     = $currency;
        $post_data['tran_id']      = $reference;
        $post_data['success_url']  = $success_url;
        $post_data['fail_url']     = $fail_url;
        $post_data['cancel_url']   = $cancel_url;

        # EMI INFO
        $post_data['emi_option']          = "1";
        $post_data['emi_max_inst_option'] = "9";
        $post_data['emi_selected_inst']   = "9";

        # CUSTOMER INFORMATION
        $post_data['cus_name']     = $output['validated']['full_name'] ?? ''??"Test Customer";
        $post_data['cus_email']    = $output['validated']['email'] ?? ''??"test@test.com";
        $post_data['cus_add1']     = "";
        $post_data['cus_add2']     = "";
        $post_data['cus_city']     = "";
        $post_data['cus_state']    = "";
        $post_data['cus_postcode'] = "";
        $post_data['cus_country']  = "";
        $post_data['cus_phone']    = "01711111111";
        $post_data['cus_fax']      = "";

        # PRODUCT INFORMATION
        $post_data['product_name']     = "Add Money";
        $post_data['product_category'] = "Add Money";
        $post_data['product_profile']  = "Add Money";
        # SHIPMENT INFORMATION
        $post_data['shipping_method'] = "NO";

         $data = [
            'request_data' => $post_data,
            'amount'       => $amount,
            'email'        => $output['validated']['email'] ?? '',
            'tx_ref'       => $reference,
            'currency'     => $currency,
            'customer'     => [
                'email'        => $output['validated']['email'] ?? '',
                "phone_number" => '',
                "name"         => $output['validated']['full_name'] ?? ''
            ],
            "customizations" => [
                "title"       => "Add Money",
                "description" => dateFormat('d M Y', Carbon::now()),
            ]
        ];

        if( $credentials->mode == Str::lower(PaymentGatewayConst::ENV_SANDBOX)){
            $link_url =  $credentials->sandbox_url;
        }else{
            $link_url =  $credentials->live_url;
        }
        # REQUEST SEND TO SSLCOMMERZ
        $direct_api_url = $link_url."/gwprocess/v4/api.php";

        $handle = curl_init();
        curl_setopt($handle, CURLOPT_URL, $direct_api_url );
        curl_setopt($handle, CURLOPT_TIMEOUT, 30);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($handle, CURLOPT_POST, 1 );
        curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, FALSE); # KEEP IT FALSE IF YOU RUN FROM LOCAL PC


        $content = curl_exec($handle);
        $result = json_decode( $content,true);

        if( $result['status']  != "SUCCESS"){
            throw new Exception($result['failedreason']);
        }

        $this->sslJunkInsert($data);
        return redirect($result['GatewayPageURL']);

    }

    public function getSslCredentials($output) {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");
        $store_id_sample = ['store_id','Store Id','store-id'];
        $store_password_sample = ['Store Password','store-password','store_password'];
        $sandbox_url_sample = ['Sandbox Url','sandbox-url','sandbox_url'];
        $live_url_sample = ['Live Url','live-url','live_url'];

        $store_id = '';
        $outer_break = false;
        foreach($store_id_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->sllPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->sllPlainText($label);

                if($label == $modify_item) {
                    $store_id = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }


        $store_password = '';
        $outer_break = false;
        foreach($store_password_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->sllPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->sllPlainText($label);

                if($label == $modify_item) {
                    $store_password = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }
        $sandbox_url = '';
        $outer_break = false;
        foreach($sandbox_url_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->sllPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->sllPlainText($label);

                if($label == $modify_item) {
                    $sandbox_url = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }
        $live_url = '';
        $outer_break = false;
        foreach($live_url_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->sllPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->sllPlainText($label);

                if($label == $modify_item) {
                    $live_url = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }

        $mode = $gateway->env;

        $paypal_register_mode = [
            PaymentGatewayConst::ENV_SANDBOX => "sandbox",
            PaymentGatewayConst::ENV_PRODUCTION => "live",
        ];
        if(array_key_exists($mode,$paypal_register_mode)) {
            $mode = $paypal_register_mode[$mode];
        }else {
            $mode = "sandbox";
        }

        return (object) [
            'store_id'     => $store_id,
            'store_password' => $store_password,
            'sandbox_url' => $sandbox_url,
            'live_url' => $live_url,
            'mode'          => $mode,

        ];

    }

    public function sllPlainText($string) {
        $string = Str::lower($string);
        return preg_replace("/[^A-Za-z0-9]/","",$string);
    }

    public function sslJunkInsert($response) {

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
            'user_id'    => $output['wallet']->user_id,
            'type'          => PaymentGatewayConst::SSLCOMMERZ,
            'identifier'    => $response['tx_ref'],
            'data'          => $data,
        ]);
    }

    public function sslcommerzSuccess($output = null) {
        if(!$output) $output = $this->output;
        $token = $this->output['tempData']['identifier'] ?? "";
        if(empty($token)) throw new Exception('Transaction failed. Record didn\'t saved properly. Please try again.');
        return $this->createTransactionSsl($output);
    }

    public function createTransactionSsl($output) {

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


        $inserted_id = $this->insertRecordSsl($output,$trx_id);
        $this->createTransactionChargeRecord($inserted_id, $output);
        $this->removeTempDataSsl($output);


        $buyer = [
            'email' => $output['validated']['email'],
            'name'  => $output['validated']['full_name'],
        ];

        if($basic_setting->email_notification == true){
            try {
                $user = $output[$type]->user;
                $user->notify(new UserNotification($user, $output, $trx_id));
                Notification::route('mail', $buyer['email'])->notify(new BuyerNotification($buyer, $output, $trx_id));
            } catch (\Exception $e) {
                //Handle Error
            }
        }
    }

    public function insertRecordSsl($output,$trx_id) {

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

            $this->updateWalletBalanceSsl($output);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }

    public function updateWalletBalanceSsl($output) {
        $update_amount = $output['receiver_wallet']->balance + $output['charge_calculation']['conversion_payable'];
        $output['receiver_wallet']->update([
            'balance'   => $update_amount,
        ]);
    }

    public function removeTempDataSsl($output) {
        TemporaryData::where("identifier",$output['tempData']['identifier'])->delete();
    }

}
