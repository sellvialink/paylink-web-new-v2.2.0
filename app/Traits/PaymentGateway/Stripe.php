<?php

namespace App\Traits\PaymentGateway;

use Exception;
use App\Traits\Transaction;
use Illuminate\Support\Str;
use App\Models\TemporaryData;
use Illuminate\Support\Carbon;
use App\Notifications\PaymentLink\Gateway\UserNotification;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\Notification;
use App\Providers\Admin\BasicSettingsProvider;
use App\Http\Helpers\Api\Helpers as ApiResponse;
use App\Notifications\PaymentLink\Gateway\BuyerNotification;

trait Stripe
{
    use Transaction;

    public function stripeInit($output = null) {
        $basic_settings = BasicSettingsProvider::get();
        if(!$output) $output = $this->output;
        $credentials = $this->getStripeCredentials($output);
        $reference = generateTransactionReference();
        $amount = $output['charge_calculation']['requested_amount'] ? number_format($output['charge_calculation']['requested_amount'],2,'.','') : 0;
        $currency = $output['charge_calculation']['sender_cur_code']??"USD";

        if($output['type'] == PaymentGatewayConst::TYPEPAYLINK){
            $return_url = route('payment-link.payment.success.stripe', $reference);
        }elseif($output['type'] == PaymentGatewayConst::TYPEPRODUCT){
            $return_url = route('product-link.payment.success.stripe', $reference);
        }else{
            $return_url =  route('invoice.payment.success.stripe', $reference);
        }

        // Enter the details of the payment
        $data = [
            'payment_options' => 'card',
            'amount'          => $amount,
            'email'           => $output['validated']['email'] ?? '',
            'tx_ref'          => $reference,
            'currency'        =>  $currency,
            'redirect_url'    => $return_url,
            'customer'        => [
                'email'        => $output['validated']['email'] ?? '',
                "phone_number" => '',
                "name"         => $output['validated']['full_name'] ?? '',
            ],
            "customizations" => [
                "title"       => "Add Money",
                "description" => dateFormat('d M Y', Carbon::now()),
            ]
        ];

       //start stripe pay link
       $stripe = new \Stripe\StripeClient($credentials->secret_key);

       //create product for Product Id
       try{
            $product_id = $stripe->products->create([
                'name' => 'Payment For ( '.$basic_settings->site_name.' )',
            ]);
       }catch(Exception $e){
            throw new Exception($e->getMessage());
       }
       //create price for Price Id
       try{
            $price_id =$stripe->prices->create([
                'currency' =>  $currency,
                'unit_amount' => $amount * 100,
                'product' => $product_id->id??""
            ]);
       }catch(Exception $e){
            throw new Exception("Something Is Wrong, Please Contact With Owner");
       }

       //create payment live links
       try{
            $payment_link = $stripe->paymentLinks->create([
                'line_items' => [
                [
                    'price' => $price_id->id,
                    'quantity' => 1,
                ],
                ],
                'after_completion' => [
                    'type' => 'redirect',
                    'redirect' => ['url' => $return_url],
                ],
            ]);
        }catch(Exception $e){
            throw new Exception("Something Is Wrong, Please Contact With Owner");
        }

        $this->stripeJunkInsert($data);


        return redirect($payment_link->url."?prefilled_email=".@$output['validated']['email']);

    }

    public function getStripeCredentials($output) {
        $gateway = $output['gateway'] ?? null;
        if(!$gateway) throw new Exception("Payment gateway not available");
        $client_id_sample = ['publishable_key','publishable key','publishable-key'];
        $client_secret_sample = ['secret id','secret-id','secret_id'];

        $client_id = '';
        $outer_break = false;
        foreach($client_id_sample as $item) {
            if($outer_break == true) {
                break;
            }
            $modify_item = $this->stripePlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->stripePlainText($label);

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
            $modify_item = $this->stripePlainText($item);
            foreach($gateway->credentials ?? [] as $gatewayInput) {
                $label = $gatewayInput->label ?? "";
                $label = $this->stripePlainText($label);

                if($label == $modify_item) {
                    $secret_id = $gatewayInput->value ?? "";
                    $outer_break = true;
                    break;
                }
            }
        }

        return (object) [
            'publish_key'     => $client_id,
            'secret_key' => $secret_id,

        ];

    }

    public function stripePlainText($string) {
        $string = Str::lower($string);
        return preg_replace("/[^A-Za-z0-9]/","",$string);
    }

    public function stripeJunkInsert($response) {
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
            'type'       => PaymentGatewayConst::STRIPE,
            'user_id'    => $output['wallet']->user_id,
            'identifier' => $response['tx_ref'],
            'data'       => $data,
        ]);
    }

    public function stripeSuccess($output = null) {
        if(!$output) $output = $this->output;
        $token = $this->output['tempData']['identifier'] ?? "";
        if(empty($token)) throw new Exception('Transaction failed. Record didn\'t saved properly. Please try again.');
        return $this->createTransactionStripe($output);
    }

    public function createTransactionStripe($output) {
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

        $inserted_id = $this->insertRecordStripe($output,$trx_id);
        $this->createTransactionChargeRecord($inserted_id, $output);
        $this->removeTempDataStripe($output);

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

    public function insertRecordStripe($output, $trx_id) {

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

            $this->updateWalletBalanceStripe($output);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }

    public function updateWalletBalanceStripe($output) {
        $update_amount = $output['receiver_wallet']->balance + $output['charge_calculation']['conversion_payable'];
        $output['receiver_wallet']->update([
            'balance'   => $update_amount,
        ]);
    }

    public function removeTempDataStripe($output) {
        $token = session()->get('identifier');
        TemporaryData::where("identifier",$token)->delete();
    }
}
