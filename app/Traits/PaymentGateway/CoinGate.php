<?php

namespace App\Traits\PaymentGateway;

use Exception;
use App\Traits\Transaction;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use Illuminate\Support\Facades\Http;
use App\Constants\PaymentGatewayConst;
use App\Models\User\Invoice;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PaymentLink\Gateway\UserNotification;
use App\Notifications\PaymentLink\Gateway\BuyerNotification;

trait CoinGate
{
    use Transaction;

    private $coinGate_gateway_credentials;
    private $coinGate_access_token;
    private $coinGate_status_paid = "paid";

    public function coingateInit($output = null) {
        if(!$output) $output = $this->output;
        $credentials = $this->getCoinGateCredentials($output);
        $request_credentials = $this->getCoinGateRequestCredentials();
        return $this->coinGateCreateOrder($request_credentials, $output);

    }
    public function getCoinGateCredentials($output) {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");

        $production_url_sample = ['live','live url','live env','live environment', 'coin gate live url','coin gate live','production url', 'production link'];
        $production_app_token_sample = ['production token','production app token','production auth token','live token','live app token','live auth token'];
        $sandbox_url_sample = ['sandbox','sandbox url','sandbox env', 'test url', 'test', 'sandbox environment', 'coin gate sandbox url', 'coin gate sandbox' , 'coin gate test'];
        $sandbox_app_token_sample = ['sandbox token','sandbox app token','test app token','test token','test auth token','sandbox auth token'];

        $production_url = $this->getValueFromGatewayCredentials($gateway,$production_url_sample);
        $production_app_token = $this->getValueFromGatewayCredentials($gateway,$production_app_token_sample);
        $sandbox_url = $this->getValueFromGatewayCredentials($gateway,$sandbox_url_sample);
        $sandbox_app_token = $this->getValueFromGatewayCredentials($gateway,$sandbox_app_token_sample);

        $mode = $gateway->env;

        $gateway_register_mode = [
            PaymentGatewayConst::ENV_SANDBOX => "sandbox",
            PaymentGatewayConst::ENV_PRODUCTION => "production",
        ];

        if(array_key_exists($mode,$gateway_register_mode)) {
            $mode = $gateway_register_mode[$mode];
        }else {
            $mode = "sandbox";
        }

        $credentials = (object) [
            'production_url'    => $production_url,
            'production_token'  => $production_app_token,
            'sandbox_url'       => $sandbox_url,
            'sandbox_token'     => $sandbox_app_token,
            'mode'              => $mode,
        ];

        $this->coinGate_gateway_credentials = $credentials;

        return $credentials;
    }
    public function getCoinGateRequestCredentials($output = null) {
        $credentials = $this->coinGate_gateway_credentials;
        if(!$output) $output = $this->output;

        $request_credentials = [];
        if($output['gateway']->env == PaymentGatewayConst::ENV_PRODUCTION) {
            $request_credentials['url']     = $credentials->production_url;
            $request_credentials['token']   = $credentials->production_token;
        }else {
            $request_credentials['url']     = $credentials->sandbox_url;
            $request_credentials['token']   = $credentials->sandbox_token;
        }
        return (object) $request_credentials;
    }
    public function registerCoinGateEndpoints() {
        return [
            'createOrder'       => 'orders',
        ];
    }

    public function getCoinGateEndpoint($name) {
        $endpoints = $this->registerCoinGateEndpoints();
        if(array_key_exists($name,$endpoints)) {
            return $endpoints[$name];
        }
        throw new Exception("Oops! Request endpoint not registered!");
    }

    public function coinGateCreateOrder($credentials, $output) {
        $request_base_url       = $credentials->url;
        $request_access_token   = $credentials->token;

        $temp_record_token = generate_unique_string('temporary_datas','identifier',60);
        $this->setUrlParams("token=" . $temp_record_token); // set Parameter to URL for identifying when return success/cancel


        if($output['type'] == PaymentGatewayConst::TYPEPAYLINK){
            $redirection = [
                "return_url"   => "payment-link.payment.coingate.payment.success",
                "cancel_url"   => "payment-link.payment.coingate.payment.cancel",
                "callback_url" => "payment-link.payment.coingate.payment.callback"
            ];
        }elseif($output['type'] == PaymentGatewayConst::TYPEPRODUCT){
            $redirection = [
                "return_url"   => "product-link.payment.coingate.payment.success",
                "cancel_url"   => "product-link.payment.coingate.payment.cancel",
                "callback_url" => "product-link.payment.coingate.payment.callback"
            ];
        }else{
            $redirection = [
                "return_url"   => "invoice.payment.coingate.payment.success",
                "cancel_url"   => "invoice.payment.coingate.payment.cancel",
                "callback_url" => "invoice.payment.coingate.payment.callback"
            ];
        }

        $url_parameter = $this->getUrlParams();
        $endpoint = $request_base_url . "/" . $this->getCoinGateEndpoint('createOrder');

        $response = Http::withToken($request_access_token)->post($endpoint,[
            'order_id'          => Str::uuid(),
            'price_amount'      => $this->output['charge_calculation']['requested_amount'],
            'price_currency'    => $this->output['charge_calculation']['sender_cur_code'],
            'receive_currency'  => $this->output['charge_calculation']['base_currency_code'],
            'callback_url'      => $this->setGatewayRoute($redirection['callback_url'],PaymentGatewayConst::COIN_GATE,$url_parameter),
            'cancel_url'        => $this->setGatewayRoute($redirection['cancel_url'],PaymentGatewayConst::COIN_GATE,$url_parameter),
            'success_url'       => $this->setGatewayRoute($redirection['return_url'],PaymentGatewayConst::COIN_GATE,$url_parameter),
        ]);

        if($response->failed()) {
            $message = json_decode($response->body(),true);
            throw new Exception($message['message']);
        }
        if($response->successful()) {
            $response_array = json_decode($response->body(),true);

            if(isset($response_array['payment_url'])) {
                // create junk transaction
                $this->coinGateJunkInsert($this->output,$response_array,$temp_record_token);

                if(request()->expectsJson()) {
                    $this->output['redirection_response']   = $response_array;
                    $this->output['redirect_links']         = [];
                    $this->output['redirect_url']           = $response_array['payment_url'];
                    return $this->get();
                }
                return redirect()->away($response_array['payment_url']);
            }
        }

        throw new Exception("Something went wrong! Please try again");

    }
    public function coinGateJunkInsert($output,$response, $temp_identifier) {

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
            'type'       => PaymentGatewayConst::COIN_GATE,
            'identifier' => $temp_identifier,
            'data'       => $data,
        ]);
    }

    public function coingateSuccess($output = null) {

        $reference = $output['tempData']['identifier'];
        $output['capture']      = $output['tempData']['data']->response ?? "";
        $output['callback_ref'] = $reference;

        // Search data on transaction table
        if(!$this->searchWithReferenceInTransaction($reference)) {
            // need to insert new transaction in database
            try{
                $this->createTransactionCoinGate($output, PaymentGatewayConst::STATUSPENDING);
            }catch(Exception $e) {
                throw new Exception($e->getMessage());
            }
        }
        if(!$this->predefined_user) {
            $this->removeTempDataCoinGate($output);
        }
    }
    public function createTransactionCoinGate($output,$status = PaymentGatewayConst::STATUSSUCCESS) {

        $basic_setting = BasicSettings::first();

        if($output['type'] == PaymentGatewayConst::TYPEPAYLINK){
            $type = 'payment_link';
            $trx_id = generateTrxString('transactions', 'trx_id', 'PL-', 8);
        }if($output['type'] == PaymentGatewayConst::TYPEPRODUCT){
            $type = 'product_link';
            $trx_id = generateTrxString('transactions', 'trx_id', 'PL-', 8);
        }else{
            $type = 'invoice';
            $trx_id = generateTrxString('transactions', 'trx_id', 'INV-', 8);
        }

        $inserted_id = $this->insertRecordCoinGate($output,$trx_id,$status);
        $this->createTransactionChildRecords($inserted_id, $output);

        $buyer = [
            'email' => $output['validated']['email'],
            'name'  => $output['validated']['full_name'],
        ];

        if($basic_setting->email_notification == true){
            try {
                $user = $output[$type]->user;
                $user->notify(new UserNotification($user, $output, $trx_id));
                Notification::route('mail', $buyer['email'])->notify(new BuyerNotification($buyer, $output, $trx_id));
            } catch (\Exception $E) {
                //Handle Error
            }
        }


    }
    public function insertRecordCoinGate($output,$trx_id,$status = PaymentGatewayConst::STATUSSUCCESS) {

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
            if($status === PaymentGatewayConst::STATUSSUCCESS) {
                $available_balance = $output['receiver_wallet']->balance + $output['charge_calculation']['conversion_payable'];
            }else{
                $available_balance = $output['receiver_wallet']->balance;
            }
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
                'callback_ref'                 => $output['callback_ref'] ?? null,
                'available_balance'           => $available_balance,
                'remark'                      => ucwords($output['type']." Transaction Successfully"),
                'details'                     => json_encode($output),
                'status'                      => $status,
                'payment_type'                => PaymentGatewayConst::TYPE_GATEWAY_PAYMENT,
                'attribute'                   => PaymentGatewayConst::RECEIVED,
                'created_at'                  => now(),
            ]);

            if($status === PaymentGatewayConst::STATUSSUCCESS) {
                $this->updateWalletBalanceCoinGate($output);

                if($output['type'] == PaymentGatewayConst::TYPEINVOICE){
                    $invoice = Invoice::find($output['validated']['target']);
                    $invoice->update(['status' => 1]);
                }

            }
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }
    public function updateWalletBalanceCoinGate($output) {
        $update_amount = $output['receiver_wallet']->balance + $output['charge_calculation']['conversion_payable'];
        $output['receiver_wallet']->update([
            'balance'   => $update_amount,
        ]);
    }

    public function removeTempDataCoinGate($output) {
        TemporaryData::where("identifier",$output['tempData']['identifier'])->delete();
    }
    public static function isCoinGate($gateway) {
        $search_keyword = ['coingate','coinGate','coingate gateway','coingate crypto gateway','crypto gateway coingate'];
        $gateway_name = $gateway->name;

        $search_text = Str::lower($gateway_name);
        $search_text = preg_replace("/[^A-Za-z0-9]/","",$search_text);
        foreach($search_keyword as $keyword) {
            $keyword = Str::lower($keyword);
            $keyword = preg_replace("/[^A-Za-z0-9]/","",$keyword);
            if($keyword == $search_text) {
                return true;
                break;
            }
        }
        return false;
    }
    public function coingateCallbackResponse($reference,$callback_data, $output = null) {

        if(!$output) $output = $this->output;
        $callback_status = $callback_data['status'] ?? "";

        if(isset($output['transaction']) && $output['transaction'] != null && $output['transaction']->status != PaymentGatewayConst::STATUSSUCCESS) { // if transaction already created & status is not success
            // Just update transaction status and update user wallet if needed
            if($callback_status == $this->coinGate_status_paid) {

                $transaction_details                        = json_decode(json_encode($output['transaction']->details),true) ?? [];
                $transaction_details['gateway_response']    = $callback_data;

                // update transaction status
                DB::beginTransaction();

                try{
                    DB::table($output['transaction']->getTable())->where('id',$output['transaction']->id)->update([
                        'status'            => PaymentGatewayConst::STATUSSUCCESS,
                        'available_balance' =>  $output['receiver_wallet']->balance + $output['charge_calculation']['conversion_payable'],
                        'callback_ref'      => $reference,
                    ]);

                    $this->updateWalletBalanceCoinGate($output);
                    DB::commit();

                }catch(Exception $e) {
                    DB::rollBack();
                    logger($e->getMessage());
                    throw new Exception($e);
                }
            }
        }else { // need to create transaction and update status if needed

            $status = PaymentGatewayConst::STATUSPENDING;

            if($callback_status == $this->coinGate_status_paid) {
                $status = PaymentGatewayConst::STATUSSUCCESS;

                if($output['type'] == PaymentGatewayConst::TYPEINVOICE){
                    $invoice = Invoice::find($output['validated']['target']);
                    $invoice->update(['status' => 1]);
                }

            }


            $this->createTransactionCoinGate($output, $status);
        }

        logger("Transaction Created Successfully ::" . $callback_data['status']);
    }


}
