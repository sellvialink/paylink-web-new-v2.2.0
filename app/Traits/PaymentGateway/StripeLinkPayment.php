<?php
namespace App\Traits\PaymentGateway;

use Exception;
use Stripe\Charge;
use Stripe\Customer;
use App\Traits\Transaction;
use Illuminate\Support\Facades\DB;
use Stripe\Stripe as StripePackage;
use App\Constants\PaymentGatewayConst;
use App\Notifications\PaymentLink\BuyerNotification;
use App\Notifications\PaymentLink\UserNotification;
use App\Providers\Admin\BasicSettingsProvider;
use App\Notifications\User\AddMoney\AddMoneyMail;
use Illuminate\Support\Facades\Notification;

trait StripeLinkPayment{

    use Transaction;

    public function stripeInit($output = null, $credentials) {
        if(!$output) $output = $this->output;

        StripePackage::setApiKey($credentials->secret_key);

        $cents = round($output['charge_calculation']['requested_amount'], 2) * 100;

        try {

            if($output['transaction_type'] == PaymentGatewayConst::TYPEPAYLINK){
                $type = 'payment_link';
                $trx_id = generateTrxString('transactions', 'trx_id', 'PL-', 8);
            }elseif($output['transaction_type'] == PaymentGatewayConst::TYPEPRODUCT){
                $type = 'product_link';
                $trx_id = generateTrxString('transactions', 'trx_id', 'PL-', 8);
            }else{
                $trx_id = generateTrxString('transactions', 'trx_id', 'INV-', 8);
                $type = 'invoice';
            }

            // Customer Create
            $customer = Customer::create(array(
                "email"  => $output['email'],
                "name"   => $output['card_name'],
                "source" => $output['token'],
            ));

            // Charge Create
            $charge = Charge::create ([
                "amount" => $cents,
                "currency" => $output['charge_calculation']['sender_cur_code'],
                "customer" => $customer->id,
                "description" => $output[$type]['title'],
            ]);

            if ($charge['status'] == 'succeeded') {
                $this->createTransactionStripe($output,$trx_id);

            $buyer = [
                'email' => $output['email'],
                'name'  => $output['card_name'],
            ];

            $basic_settings = BasicSettingsProvider::get();
            if($basic_settings->email_notification == true){
                $user = $output[$type]->user;
                try {
                    $user->notify(new UserNotification($user, $output, $trx_id));
                    Notification::route('mail', $buyer['email'])->notify(new BuyerNotification($buyer, $output, $trx_id));
                } catch (\Exception $e) {
                    // Handle Error
                }
            }

               return true;
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }


    }


    public function createTransactionStripe($output, $trx_id) {
        $trx_id =  $trx_id;
        try {
            $inserted_id = $this->insertRecordStripe($output,$trx_id);
            $this->createTransactionChildRecords($inserted_id, $output);
            return true;
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }

    }

    public function insertRecordStripe($output, $trx_id) {

        $trx_id = $trx_id;

        if($output['transaction_type'] == PaymentGatewayConst::TYPEPAYLINK){
            $type = 'payment_link';
            $transaction_col = 'payment_link_id';
        }elseif($output['transaction_type'] == PaymentGatewayConst::TYPEPRODUCT){
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
                'payment_gateway_currency_id' => NULL,
                'type'                        => $output['transaction_type'],
                'trx_id'                      => $trx_id,
                'request_amount'              => $output['charge_calculation']['requested_amount'],
                'payable'                     => $output['charge_calculation']['payable'],
                'conversion_payable'          => $output['charge_calculation']['conversion_payable'],
                'request_amount_admin'        => $output['charge_calculation']['request_amount_admin'],
                'available_balance'           => $output['receiver_wallet']->balance + $output['charge_calculation']['conversion_payable'],
                'remark'                      => ucwords($output['transaction_type']." Transaction Successfully"),
                'details'                     => json_encode($output),
                'status'                      => true,
                'payment_type'                => PaymentGatewayConst::TYPE_CARD_PAYMENT,
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


}
