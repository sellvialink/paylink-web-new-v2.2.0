<?php

namespace App\Traits\PaymentGateway;

use Exception;
use App\Traits\Transaction;
use Illuminate\Support\Str;
use App\Models\User\Invoice;
use App\Models\TemporaryData;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Http\Helpers\PaymentGateway;
use Illuminate\Support\Facades\Auth;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\Notification;
use App\Providers\Admin\BasicSettingsProvider;
use App\Notifications\PaymentLink\Gateway\UserNotification;
use App\Notifications\PaymentLink\Gateway\BuyerNotification;


trait PerfectMoney {

    use Transaction;

    private $perfect_money_credentials;
    private $perfect_money_request_credentials;

    public function perfectMoneyInit($output = null)
    {
        if(!$output) $output = $this->output;
        $gateway_credentials = $this->perfectMoneyGatewayCredentials($output['gateway']);
        $request_credentials = $this->perfectMoneyRequestCredentials($gateway_credentials, $output['gateway'], $output['currency']);
        $output['request_credentials'] = $request_credentials;

        if($gateway_credentials->passphrase == "") {
            throw new Exception("You must set Alternate Passphrase under Settings section in your Perfect Money account before starting receiving payment confirmations.");
        }

        // need to insert junk for temporary data
        $temp_record        = $this->perfectMoneyJunkInsert($output);
        $temp_identifier    = $temp_record->identifier;

        if($output['type'] == PaymentGatewayConst::TYPEPAYLINK){
            $link_for_redirect_form = route('payment-link.payment.payment.redirect.form', [PaymentGatewayConst::PERFECT_MONEY, 'token' => $temp_identifier]);
        }elseif($output['type'] == PaymentGatewayConst::TYPEPRODUCT){
            $link_for_redirect_form = route('product-link.payment.payment.redirect.form', [PaymentGatewayConst::PERFECT_MONEY, 'token' => $temp_identifier]);
        }else{
            $link_for_redirect_form = route('invoice.payment.payment.redirect.form', [PaymentGatewayConst::PERFECT_MONEY, 'token' => $temp_identifier]);
        }

        return redirect()->away($link_for_redirect_form);
    }


    /**
     * Get payment gateway credentials for both sandbox and production
     */
    public function perfectMoneyGatewayCredentials($gateway)
    {
        if(!$gateway) throw new Exception("Oops! Payment Gateway Not Found!");

        $usd_account_sample     = ['usd account','usd','usd wallet','account usd'];
        $eur_account_sample     = ['eur account','eur','eur wallet', 'account eur'];
        $pass_phrase_sample     = ['alternate passphrase' ,'passphrase', 'perfect money alternate passphrase', 'alternate passphrase perfect money' , 'alternate phrase' , 'alternate pass'];

        $usd_account            = PaymentGateway::getValueFromGatewayCredentials($gateway,$usd_account_sample);
        $eur_account            = PaymentGateway::getValueFromGatewayCredentials($gateway,$eur_account_sample);
        $pass_phrase            = PaymentGateway::getValueFromGatewayCredentials($gateway,$pass_phrase_sample);

        $credentials = (object) [
            'usd_account'   => $usd_account,
            'eur_account'   => $eur_account,
            'passphrase'    => $pass_phrase, // alternate passphrase
        ];

        $this->perfect_money_credentials = $credentials;

        return $credentials;
    }

    /**
     * Get payment gateway credentials for making api request
     */
    public function perfectMoneyRequestCredentials($gateway_credentials, $payment_gateway, $gateway_currency)
    {
        if($gateway_currency->currency_code == "EUR") {
            $request_credentials = [
                'account'   => $gateway_credentials->eur_account
            ];
        }else if($gateway_currency->currency_code == "USD") {
            $request_credentials = [
                'account'   => $gateway_credentials->usd_account
            ];
        }

        $request_credentials = (object) $request_credentials;

        $this->perfect_money_request_credentials = $request_credentials;

        return $request_credentials;
    }

    public function perfectMoneyJunkInsert($output)
    {
        $action_type = PaymentGatewayConst::REDIRECT_USING_HTML_FORM;

        $payment_id = Str::uuid() . '-' . time();
        $this->setUrlParams("token=" . $payment_id); // set Parameter to URL for identifying when return success/cancel

        $redirect_form_data = $this->makingPerfectMoneyRedirectFormData($output, $payment_id);
        $form_action_url    = "https://perfectmoney.com/api/step1.asp";
        $form_method        = "POST";

        // dd($output);

        $data = [
            'gateway'            => $output['gateway']->id,
            'currency'           => $output['currency']->id,
            'charge_calculation' => json_decode(json_encode($output['charge_calculation']),true),
            'wallet_table'       => $output['wallet']->getTable(),
            'wallet_id'          => $output['wallet']->id,
            'action_type'        => $action_type,
            'redirect_form_data' => $redirect_form_data,
            'action_url'         => $form_action_url,
            'form_method'        => $form_method,
            'validated'          => $output['validated'],
        ];

        return TemporaryData::create([
            'user_id'    => $output['wallet']->user_id,
            'type'       => PaymentGatewayConst::PERFECT_MONEY,
            'identifier' => $payment_id,
            'data'       => $data,
        ]);
    }

    public function makingPerfectMoneyRedirectFormData($output, $payment_id)
    {
        $basic_settings = BasicSettingsProvider::get();

        $url_parameter = $this->getUrlParams();

        $total_amount = number_format($output['charge_calculation']['requested_amount'], 2, '.', '');

        if($output['type'] == PaymentGatewayConst::TYPEPAYLINK){
            $redirection = [
                'callback' => 'payment-link.payment.perfect.payment.callback',
                'success'  => 'payment-link.payment.perfect.payment.success',
                'cancel'   => 'payment-link.payment.perfect.payment.cancel',
            ];
        }elseif($output['type'] == PaymentGatewayConst::TYPEPRODUCT){
            $redirection = [
                'callback' => 'product-link.payment.perfect.payment.callback',
                'success'  => 'product-link.payment.perfect.payment.success',
                'cancel'   => 'product-link.payment.perfect.payment.cancel',
            ];
        }else{
            $redirection = [
                'callback' => 'invoice.payment.perfect.payment.callback',
                'success'  => 'invoice.payment.perfect.payment.success',
                'cancel'   => 'invoice.payment.perfect.payment.cancel',
            ];
        }

        return [
            [
                'name'  => 'PAYEE_ACCOUNT',
                'value' => $output['request_credentials']->account,
            ],
            [
                'name'  => 'PAYEE_NAME',
                'value' => $basic_settings->site_name,
            ],
            [
                'name'  => 'PAYMENT_AMOUNT',
                'value' => $total_amount,
            ],
            [
                'name'  => 'PAYMENT_UNITS',
                'value' => $output['currency']->currency_code,
            ],
            [
                'name'  => 'PAYMENT_ID',
                'value' => $payment_id,
            ],
            [
                'name'  => 'STATUS_URL',
                'value' => $this->setGatewayRoute($redirection['callback'],PaymentGatewayConst::PERFECT_MONEY,$url_parameter),
            ],
            [
                'name'  => 'PAYMENT_URL',
                'value' => $this->setGatewayRoute($redirection['success'],PaymentGatewayConst::PERFECT_MONEY,$url_parameter),
            ],
            [
                'name'  => 'PAYMENT_URL_METHOD',
                'value' => 'GET',
            ],
            [
                'name'  => 'NOPAYMENT_URL',
                'value' => $this->setGatewayRoute($redirection['cancel'],PaymentGatewayConst::PERFECT_MONEY,$url_parameter),
            ],
            [
                'name'  => 'NOPAYMENT_URL_METHOD',
                'value' => 'GET',
            ],
            [
                'name'  => 'BAGGAGE_FIELDS',
                'value' => '',
            ],
            [
                'name'  => 'INTERFACE_LANGUAGE',
                'value' => 'en_US',
            ],
            [
                'name'  => 'r-source',
                'value' => 'APP',
            ],
        ];
    }

    public function isPerfectMoney($gateway)
    {
        $search_keyword = ['perfectmoney','perfect money','perfect-money','perfect money gateway', 'perfect money payment gateway'];
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

    public function getPerfectMoneyAlternatePassphrase($gateway)
    {
        $gateway_credentials = $this->perfectMoneyGatewayCredentials($gateway);
        return $gateway_credentials->passphrase;
    }

    public function perfectmoneySuccess($output) {

        $reference              = $output['tempData']['identifier'];
        $output['capture']      = $output['tempData']['data']->callback_data ?? "";
        $output['callback_ref'] = $reference;

        $pass_phrase = strtoupper(md5($this->getPerfectMoneyAlternatePassphrase($output['gateway'])));

        if($output['capture'] != "") {

            $concat_string = $output['capture']->PAYMENT_ID . ":" . $output['capture']->PAYEE_ACCOUNT . ":" . $output['capture']->PAYMENT_AMOUNT . ":" . $output['capture']->PAYMENT_UNITS . ":" . $output['capture']->PAYMENT_BATCH_NUM . ":" . $output['capture']->PAYER_ACCOUNT . ":" . $pass_phrase . ":" . $output['capture']->TIMESTAMPGMT;

            $md5_string = strtoupper(md5($concat_string));

            $v2_hash = $output['capture']->V2_HASH;

            if($md5_string == $v2_hash) {
                // this transaction is success
                if(!$this->searchWithReferenceInTransaction($reference)) {
                    // need to insert new transaction in database
                    try{
                        $this->createTransactionPerfect($output, PaymentGatewayConst::STATUSSUCCESS);
                        $this->removeTempDataPerfect($output);
                    }catch(Exception $e) {
                        throw new Exception($e->getMessage());
                    }
                }
            }
        }

    }

    public function createTransactionPerfect($output,$status = PaymentGatewayConst::STATUSSUCCESS) {
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


        $inserted_id = $this->insertRecordPerfect($output,$trx_id,$status);
        $this->createTransactionChildRecords($inserted_id, $output);

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

    public function insertRecordPerfect($output,$trx_id,$status = PaymentGatewayConst::STATUSSUCCESS) {
        DB::beginTransaction();
        try{
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
                $this->updateWalletBalancePerfect($output);

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

    public function updateWalletBalancePerfect($output) {
        $update_amount = $output['receiver_wallet']->balance + $output['charge_calculation']['conversion_payable'];
        $output['receiver_wallet']->update([
            'balance'   => $update_amount,
        ]);
    }

    public function removeTempDataPerfect($output) {
        TemporaryData::where("identifier",$output['tempData']['identifier'])->delete();
    }


    public function perfectmoneyCallbackResponse($reference,$callback_data, $output = null) {

        if(!$output) $output = $this->output;
        $pass_phrase = strtoupper(md5($this->getPerfectMoneyAlternatePassphrase($output['gateway'])));

        if(is_array($callback_data) && count($callback_data) > 0) {
            $concat_string = $callback_data['PAYMENT_ID'] . ":" . $callback_data['PAYEE_ACCOUNT'] . ":" . $callback_data['PAYMENT_AMOUNT'] . ":" . $callback_data['PAYMENT_UNITS'] . ":" . $callback_data['PAYMENT_BATCH_NUM'] . ":" . $callback_data['PAYER_ACCOUNT'] . ":" . $pass_phrase . ":" . $callback_data['TIMESTAMPGMT'];

            $md5_string = strtoupper(md5($concat_string));
            $v2_hash = $callback_data['V2_HASH'];

            if($md5_string != $v2_hash) {
                return false;
                logger("Transaction hash did not match. ref: $reference", [$callback_data]);
            }
        }else {
            return false;
            logger("Invalid callback data. ref: $reference", [$callback_data]);
        }

        if(isset($output['transaction']) && $output['transaction'] != null && $output['transaction']->status != PaymentGatewayConst::STATUSSUCCESS) { // if transaction already created & status is not success

            // Just update transaction status and update user wallet if needed
            $transaction_details                        = json_decode(json_encode($output['transaction']->details),true) ?? [];
            $transaction_details['gateway_response']    = $callback_data;

            // update transaction status
            DB::beginTransaction();

            try{
                DB::table($output['transaction']->getTable())->where('id',$output['transaction']->id)->update([
                    'status'        => PaymentGatewayConst::STATUSSUCCESS,
                    'details'       => json_encode($transaction_details),
                    'callback_ref'  => $reference,
                ]);

                $this->updateWalletBalancePerfect($output);
                DB::commit();

            }catch(Exception $e) {
                DB::rollBack();
                logger($e);
                throw new Exception($e);
            }
        }else { // need to create transaction and update status if needed

            $status = PaymentGatewayConst::STATUSSUCCESS;

            if($output['type'] == PaymentGatewayConst::TYPEINVOICE){
                $invoice = Invoice::find($output['validated']['target']);
                $invoice->update(['status' => 1]);
            }

            $this->createTransactionPerfect($output, $status);
        }

        logger("Transaction Created Successfully! ref: " . $reference);
    }
}
