<?php

namespace App\Traits\PaymentGateway;

use Exception;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use App\Http\Helpers\Pagadito;
use App\Http\Helpers\Api\Helpers;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use Illuminate\Support\Facades\Auth;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Notification;
use App\Providers\Admin\BasicSettingsProvider;
use App\Notifications\User\AddMoney\AddMoneyMail;
use App\Notifications\PaymentLink\Gateway\UserNotification;
use App\Notifications\PaymentLink\Gateway\BuyerNotification;


trait PagaditoTrait
{
    public function pagaditoInit($output = null) {
        $basic_settings = BasicSettingsProvider::get();
        if(!$output) $output = $this->output;
        $credentials = $this->getPagaditoCredentials($output);
        $this->pagaditoSetSecreteKey($credentials);
        $uid = $credentials->uid;
        $wsk = $credentials->wsk;
        $mode = $credentials->mode;
        $Pagadito = new Pagadito($uid,$wsk,$credentials,$output['charge_calculation']['sender_cur_code']);
        $Pagadito->config( $credentials,$output['charge_calculation']['sender_cur_code']);

        if ($mode == "sandbox") {
            $Pagadito->mode_sandbox_on();
        }
        $title = 'Wallet Add';
        if ($Pagadito->connect()) {
            $Pagadito->add_detail(1,"Please Pay For ".$basic_settings->site_name." ".$title. " Balance", $output['charge_calculation']['requested_amount']);
            $Pagadito->set_custom_param("param1", "Valor de param1");
            $Pagadito->set_custom_param("param2", "Valor de param2");
            $Pagadito->set_custom_param("param3", "Valor de param3");
            $Pagadito->set_custom_param("param4", "Valor de param4");
            $Pagadito->set_custom_param("param5", "Valor de param5");

            $Pagadito->enable_pending_payments();
            $getUrls = (object)$Pagadito->exec_trans($Pagadito->get_rs_code());

            if($getUrls->code == "PG1002" ){
                $parts = parse_url($getUrls->value);
                parse_str($parts['query'], $query);
                // Extract the token value
                if (isset($query['token'])) {
                    $tokenValue = $query['token'];
                } else {
                    $tokenValue = '';
                }
                $this->pagaditoJunkInsert($getUrls,$tokenValue);
                return redirect($getUrls->value);

            }
            $ern = rand(1000, 2000);
            if (!$Pagadito->exec_trans($ern)) {
                switch($Pagadito->get_rs_code())
                {
                    case "PG2001":
                        /*Incomplete data*/
                    case "PG3002":
                        /*Error*/
                    case "PG3003":
                        /*Unregistered transaction*/
                    case "PG3004":
                        /*Match error*/
                    case "PG3005":
                        /*Disabled connection*/
                    default:
                        throw new Exception($Pagadito->get_rs_code().": ".$Pagadito->get_rs_message());
                        break;
                }
            }

            return redirect($Pagadito->exec_trans($Pagadito->get_rs_code()));
        } else {
            switch($Pagadito->get_rs_code())
            {
                case "PG2001":
                    /*Incomplete data*/
                case "PG3001":
                    /*Problem connection*/
                case "PG3002":
                    /*Error*/
                case "PG3003":
                    /*Unregistered transaction*/
                case "PG3005":
                    /*Disabled connection*/
                case "PG3006":
                    /*Exceeded*/
                default:
                    throw new Exception($Pagadito->get_rs_code().": ".$Pagadito->get_rs_message());
                    break;
            }

        }


    }
    // Get Pagadito credentials
    public function getPagaditoCredentials($output) {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");

        $uid_sample = ['UID','uid','u_id'];
        $wsk_sample = ['WSK','wsk','w_sk'];
        $live_base_url_sample = ['Live Base URL','live_base_url','live-base-url', 'live base url'];
        $sandbox_base_url_sample = ['Sandbox Base URL','sandbox_base_url','sandbox-base-url', 'sandbox base url'];

        $uid = '';
        $outer_break = false;
        foreach($uid_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->pagaditoPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->pagaditoPlainText($label);
                if($label == $modify_item) {
                    $uid = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }

        $wsk = '';
        $outer_break = false;
        foreach($wsk_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->pagaditoPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->pagaditoPlainText($label);

                if($label == $modify_item) {
                    $wsk = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }

        $base_url_live = '';
        $outer_break = false;
        foreach($live_base_url_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->pagaditoPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->pagaditoPlainText($label);

                if($label == $modify_item) {
                    $base_url_live = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }

        $base_url_sandbox = '';
        $outer_break = false;
        foreach($sandbox_base_url_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->pagaditoPlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->pagaditoPlainText($label);

                if($label == $modify_item) {
                    $base_url_sandbox = $gatewayInput->value ?? "";
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

        switch ($mode) {
            case 'sandbox':
                $base_url = $base_url_sandbox;
                break;

            default:
                $base_url = $base_url_live;
                break;
        }

        return (object) [
            'uid'      => $uid,
            'wsk'      => $wsk,
            'base_url' => $base_url,
            'mode'     => $mode,
        ];

    }

    public function pagaditoPlainText($string) {
        $string = Str::lower($string);
        return preg_replace("/[^A-Za-z0-9]/","",$string);
    }

    public function pagaditoSetSecreteKey($credentials){
        Config::set('pagadito.UID',$credentials->uid);
        Config::set('pagadito.WSK',$credentials->wsk);
        if($credentials->mode == "sandbox"){
            Config::set('pagadito.SANDBOX',true);
        }else{
            Config::set('pagadito.SANDBOX',false);
        }
    }

    public function pagaditoJunkInsert($response,$tokenValue) {

        $output = $this->output;
        $wallet_table = $output['wallet']->getTable();
        $wallet_id = $output['wallet']->id;

        $data = [
            'type'               => $output['type'],
            'gateway'            => $output['gateway']->id,
            'currency'           => $output['currency']->id,
            'validated'          => $output['validated'],
            'charge_calculation' => json_decode(json_encode($output['charge_calculation']),true),
            'response'           => $response,
            'wallet_table'       => $wallet_table,
            'wallet_id'          => $wallet_id,
            'creator_guard'      => get_auth_guard(),
        ];

        Session::put('output',$output);

        return TemporaryData::create([
            'type'       => PaymentGatewayConst::PAGADITO,
            'user_id'    => $output['wallet']->user_id,
            'identifier' => $tokenValue == '' ? generate_unique_string("transactions","trx_id",16): $tokenValue,
            'data'       => $data,
        ]);
    }

    public function pagaditoSuccess($output = null) {
        if(!$output) $output = $this->output;
        $token = $this->output['tempData']['identifier'] ?? "";
        if(empty($token)) throw new Exception('Transaction Failed. Record didn\'t saved properly. Please try again.');
        return $this->createTransactionPagadito($output);
    }

    public function createTransactionPagadito($output) {

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

        $inserted_id = $this->insertRecordPagadito($output,$trx_id);
        $this->createTransactionChildRecords($inserted_id, $output);
        $this->removeTempDataPagadito($output);

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
                //Handle Error;
            }
        }

    }

    public function insertRecordPagadito($output,$trx_id) {

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

            $this->updateWalletBalancePagadito($output);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }

    public function updateWalletBalancePagadito($output) {
        $update_amount = $output['receiver_wallet']->balance + $output['charge_calculation']['conversion_payable'];
        $output['receiver_wallet']->update([
            'balance'   => $update_amount,
        ]);
    }

    public function removeTempDataPagadito($output) {
        TemporaryData::where("identifier",$output['tempData']['identifier'])->delete();
    }
}
