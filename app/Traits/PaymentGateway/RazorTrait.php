<?php
namespace App\Traits\PaymentGateway;

use Exception;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
use App\Models\User\Invoice;
use App\Models\TemporaryData;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Http\Helpers\PaymentGateway;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\PaymentGatewayCurrency;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Notification;
use App\Notifications\PaymentLink\Gateway\UserNotification;
use App\Notifications\PaymentLink\Gateway\BuyerNotification;
use App\Traits\Transaction as ChildTransaction;

trait RazorTrait  {

    use ChildTransaction;

    private $razorpay_gateway_credentials;
    private $request_credentials;
    private $razorpay_api_base_url  = "https://api.razorpay.com/";
    private $razorpay_api_v1        = "v1";
    private $razorpay_btn_pay       = true;

    public function razorInit($output) {
        if(!$output) $output = $this->output;
        $request_credentials = $this->getRazorpayRequestCredentials($output);
        try{
            if($this->razorpay_btn_pay) {
                // create link for btn pay
                return $this->razorpayCreateLinkForBtnPay($output);
            }
            return $this->createRazorpayPaymentLink($output, $request_credentials);
        }catch(Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Create Link for Button Pay (JS Checkout)
     */
    public function razorpayCreateLinkForBtnPay($output)
    {
        $temp_record_token = generate_unique_string('temporary_datas','identifier',35);
        $this->razorPayJunkInsert($temp_record_token); // create temporary information

        $btn_link = $this->generateLinkForBtnPay($temp_record_token, PaymentGatewayConst::RAZORPAY);

        if(request()->expectsJson()) {
            $this->output['redirection_response']   = [];
            $this->output['redirect_links']         = [];
            $this->output['redirect_url']           = $btn_link;
            return $this->get();
        }

        return redirect($btn_link);
    }

    /**
     * Button Pay page redirection with necessary data
     */
    public function razorpayBtnPay($temp_data)
    {
        $data = $temp_data->data;
        $output = $this->output;

        if(!isset($data->razorpay_order)) { // is order is not created the create new order
            // Need to create order
            $order = $this->razorpayCreateOrder([
                'amount'            => get_amount($data->charge_calculation->requested_amount, null, 2) * 100,
                'currency'          => $data->charge_calculation->sender_cur_code,
                'receipt'           => $temp_data->identifier,
                'partial_payment'   => false,
            ]);

            // Update TempData
            $update_data = json_decode(json_encode($data), true);
            $update_data['razorpay_order'] = $order;

            $temp_data->update([
                'data'  => $update_data,
            ]);

            $temp_data->refresh();
        }

        $data = $temp_data->data; // update the data variable
        $order = $data->razorpay_order;

        $order_id                   = $order->id;
        $request_credentials        = $this->getRazorpayRequestCredentials($output);
        $output['order_id']         = $order_id;
        $output['key']              = $request_credentials->key_id;
        $output['callback_url']     = $data->callback_url;
        $output['cancel_url']       = $data->cancel_url;
        $output['user']             = auth()->guard(get_auth_guard())->user();

        return view('user.payment-gateway.btn-pay.razorpay', compact('output'));
    }

    /**
     * Create order for receive payment
     */
    public function razorpayCreateOrder($request_data, $output = null)
    {
        if($output == null) $output = $this->output;

        $endpoint = $this->razorpay_api_base_url . $this->razorpay_api_v1 . "/orders";

        $request_credentials = $this->getRazorpayRequestCredentials($output);

        $key_id = $request_credentials->key_id;
        $secret_key = $request_credentials->secret_key;

        $response = Http::withBasicAuth($key_id, $secret_key)->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($endpoint, $request_data)->throw(function(Response $response, RequestException $exception) {
            $response_body = json_decode(json_encode($response->json()), true);
            throw new Exception($response_body['error']['description'] ?? "");
        })->json();

        return $response;
    }

    public function createRazorpayPaymentLink($output, $request_credentials)
    {
        $endpoint = $this->razorpay_api_base_url . $this->razorpay_api_v1 . "/payment_links";

        $key_id = $request_credentials->key_id;
        $secret_key = $request_credentials->secret_key;

        $temp_record_token = generate_unique_string('temporary_datas','identifier',35);
        $this->setUrlParams("token=" . $temp_record_token); // set Parameter to URL for identifying when return success/cancel

        $redirection = $this->getRedirection();
        $url_parameter = $this->getUrlParams();

        $user = auth()->guard(get_auth_guard())->user();

        $temp_data = $this->razorPayJunkInsert($temp_record_token, $output); // create temporary information

        $response = Http::withBasicAuth($key_id, $secret_key)->withHeaders([
            'Content-Type' => 'application/json',
        ])->post($endpoint, [
            'amount' => ceil($output['charge_calculation']['requested_amount']) * 100,
            'currency' => $output['charge_calculation']['sender_cur_code'],
            'expire_by' => now()->addMinutes(20)->timestamp,
            'reference_id' => $temp_record_token,
            'description' => 'Add Money',
            'customer' => [
                'name' => $user->firstname ?? "",
                'email' => $user->email ?? "",
            ],
            'notify' => [
                'sms' => false,
                'email' => true,
            ],
            'reminder_enable' => true,
            'callback_url' => $this->setGatewayRoute($redirection['return_url'],PaymentGatewayConst::RAZORPAY,$url_parameter),
            'callback_method' => 'get',
        ])->throw(function(Response $response, RequestException $exception) use ($temp_data) {
            $response_body = json_decode(json_encode($response->json()), true);
            $temp_data->delete();
            throw new Exception($response_body['error']['description'] ?? "");
        })->json();

        $response_array = json_decode(json_encode($response), true);

        $temp_data_contains = json_decode(json_encode($temp_data->data),true);
        $temp_data_contains['response'] = $response_array;

        $temp_data->update([
            'data'  => $temp_data_contains,
        ]);

        return redirect()->away($response_array['short_url']);
    }

    public function razorPayJunkInsert($temp_token)
    {
        $output = $this->output;
        $this->setUrlParams("token=" . $temp_token); // set Parameter to URL for identifying when return success/cancel

        if($output['type'] == PaymentGatewayConst::TYPEPAYLINK){
            $redirection = [
                'return_url' => 'payment-link.payment.payment.global.success',
                'cancel_url'  => 'payment-link.payment.payment.global.cancel',
            ];
        }elseif($output['type'] == PaymentGatewayConst::TYPEPRODUCT){
            $redirection = [
                'return_url' => 'product-link.payment.payment.global.success',
                'cancel_url'  => 'product-link.payment.payment.global.cancel',
            ];
        }else{
            $redirection = [
                'return_url' => 'invoice.payment.payment.global.success',
                'cancel_url'  => 'invoice.payment.payment.global.cancel',
            ];
        }

        $url_parameter = $this->getUrlParams();

        $data = [
            'gateway'            => $output['gateway']->id,
            'currency'           => $output['currency']->id,
            'validated'          => $output['validated'],
            'type'               => $output['type'],
            'charge_calculation' => json_decode(json_encode($output['charge_calculation']),true),
            'wallet_table'       => $output['wallet']->getTable(),
            'wallet_id'          => $output['wallet']->id,
            'creator_guard'      => get_auth_guard(),
            'callback_url'       => $this->setGatewayRoute($redirection['return_url'],PaymentGatewayConst::RAZORPAY,$url_parameter),
            'cancel_url'         => $this->setGatewayRoute($redirection['cancel_url'],PaymentGatewayConst::RAZORPAY,$url_parameter),
        ];

        return TemporaryData::create([
            'type'       => PaymentGatewayConst::RAZORPAY,
            'user_id'    => $output['wallet']->user_id,
            'identifier' => $temp_token,
            'data'       => $data,
        ]);
    }

    public function getRazorpayCredentials($output)
    {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");

        $key_id             = ['public key','razorpay public key','key id','razorpay public', 'public'];
        $secret_key_sample  = ['secret','secret key','razorpay secret','razorpay secret key'];

        $key_id             = PaymentGateway::getValueFromGatewayCredentials($gateway,$key_id);
        $secret_key         = PaymentGateway::getValueFromGatewayCredentials($gateway,$secret_key_sample);

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
            'key_id'                    => $key_id,
            'secret_key'                => $secret_key,
            'mode'                      => $mode
        ];

        $this->razorpay_gateway_credentials = $credentials;

        return $credentials;
    }

    public function getRazorpayRequestCredentials($output = null)
    {
        if(!$this->razorpay_gateway_credentials) $this->getRazorpayCredentials($output);
        $credentials = $this->razorpay_gateway_credentials;
        if(!$output) $output = $this->output;

        $request_credentials = [];
        $request_credentials['key_id']          = $credentials->key_id;
        $request_credentials['secret_key']      = $credentials->secret_key;

        $this->request_credentials = (object) $request_credentials;
        return (object) $request_credentials;
    }

    public function isRazorpay($gateway)
    {
        $search_keyword = ['razorpay','razorpay gateway','gateway razorpay','razorpay payment gateway'];
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

    /**
     * Razorpay Success Response
     */
    public function razorpaySuccess($output)
    {
        $reference              = $output['tempData']['identifier'];
        $order_info             = $output['tempData']['data']->razorpay_order ?? false;
        $output['callback_ref'] = $reference;

        if($order_info == false) {
            throw new Exception("Invalid Order");
        }

        $redirect_response = $output['tempData']['data']->callback_data ?? false;
        if($redirect_response == false) {
            throw new Exception("Invalid response");
        }

        if(isset($redirect_response->razorpay_payment_id) && isset($redirect_response->razorpay_order_id) && isset($redirect_response->razorpay_signature)) {
            // Response Data
            $output['capture']      = $output['tempData']['data']->callback_data ?? "";

            $gateway_credentials = $this->getRazorpayRequestCredentials($output);

            // Need to verify payment signature
            $request_order_id       = $order_info->id; // database order
            $razorpay_payment_id    = $redirect_response->razorpay_payment_id; // response payment id
            $key_secret             = $gateway_credentials->secret_key;

            $generated_signature = hash_hmac("sha256", $request_order_id . "|" . $razorpay_payment_id, $key_secret);

            if($generated_signature == $redirect_response->razorpay_signature) {

                if(!$this->searchWithReferenceInTransaction($reference)) {
                    try{
                        $this->createTransactionRazorpay($output, PaymentGatewayConst::STATUSPENDING);
                    }catch(Exception $e) {
                        throw new Exception($e->getMessage());
                    }
                }

            }else {
                throw new Exception("Payment Failed, Invalid Signature Found!");
            }

        }
    }
    public function createTransactionRazorpay($output,$status = PaymentGatewayConst::STATUSSUCCESS) {

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

        $inserted_id = $this->insertRecordRazorpay($output,$trx_id,$status);
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
            } catch (\Exception $e) {
                //Handle Error;
            }
        }


    }
    public function insertRecordRazorpay($output,$trx_id,$status = PaymentGatewayConst::STATUSSUCCESS) {

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
                $this->updateWalletBalanceRazorpay($output);

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
    public function updateWalletBalanceRazorpay($output) {
        $update_amount = $output['receiver_wallet']->balance + $output['charge_calculation']['conversion_payable'];
        $output['receiver_wallet']->update([
            'balance'   => $update_amount,
        ]);
    }

    public function removeTempDataRazorpay($output) {
        TemporaryData::where("identifier",$output['tempData']['identifier'])->delete();
    }

    /**
     * Razorpay Callback Response
     */
    public function razorpayCallbackResponse($response_data, $gateway)
    {
        logger('ok');
        $entity = $response_data['entity'] ?? false;
        $event  = $response_data['event'] ?? false;

        if($entity == "event" && $event == "order.paid") { // order response event data is valid
            // get the identifier
            $token = $response_data['payload']['order']['entity']['receipt'] ?? "";

            $temp_data = TemporaryData::where('identifier', $token)->first();

            // if transaction is already exists need to update status, balance & response data
            $transaction = Transaction::where('callback_ref', $token)->first();

            $status = PaymentGatewayConst::STATUSSUCCESS;

            if($temp_data) {
                $gateway_currency_id = $temp_data->data->currency ?? null;
                $gateway_currency = PaymentGatewayCurrency::find($gateway_currency_id);
                if($gateway_currency) {

                    $requested_amount = $temp_data['data']->transactoin->requested_amount ?? 0;
                    $validator_data = [
                        $this->currency_input_name  => $gateway_currency->alias,
                        $this->amount_input         => $requested_amount,
                        $this->target              => $temp_data['data']->validated->target,
                        $this->email               => $temp_data['data']->validated->email,
                        $this->full_name           => $temp_data['data']->validated->full_name,
                    ];

                    $get_wallet_model = PaymentGatewayConst::registerWallet()[$temp_data->data->creator_guard];
                    $user_wallet = $get_wallet_model::find($temp_data->data->wallet_id);
                    $this->predefined_user_wallet = $user_wallet;
                    $this->predefined_guard = $user_wallet->user->modelGuardName();
                    $this->predefined_user = $user_wallet->user;

                    $this->output['tempData'] = $temp_data;
                }

                $this->request_data = $validator_data;
                $this->gateway();
            }

            $output                     = $this->output;
            $output['callback_ref']     = $token;
            $output['capture']          = $response_data;

            if($transaction && $transaction->status != PaymentGatewayConst::STATUSSUCCESS) {

                $update_data                        = json_decode(json_encode($transaction->details), true);
                $update_data['gateway_response']    = $response_data;

                // update information
                $transaction->update([
                    'status'            => $status,
                    'available_balance' => $output['receiver_wallet']->balance + $output['charge_calculation']['conversion_payable'],
                    'details'           => $update_data
                ]);

                if($output['type'] == PaymentGatewayConst::TYPEINVOICE){
                    $invoice = Invoice::find($output['validated']['target']);
                    $invoice->update(['status' => 1]);
                }

                // update balance
                $this->updateWalletBalanceRazorpay($output);
            }else {
                // create new transaction with success
                $this->createTransactionRazorpay($output, $status);
            }

        }

        logger('end');

        logger("Transaction Created Successfully!");
    }
}
