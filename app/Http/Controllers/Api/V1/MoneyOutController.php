<?php

namespace App\Http\Controllers\Api\V1;

use Exception;
use App\Models\UserWallet;
use App\Models\Transaction;
use Jenssegers\Agent\Agent;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Constants\NotificationConst;
use App\Http\Controllers\Controller;
use App\Models\Admin\PaymentGateway;
use Illuminate\Support\Facades\Auth;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\AdminNotification;
use App\Traits\ControlDynamicInputFields;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use App\Models\Admin\PaymentGatewayCurrency;
use App\Http\Helpers\Api\Helpers as ApiResponse;
use App\Notifications\User\Moneyout\MoneyoutMail;

class MoneyOutController extends Controller
{
    use ControlDynamicInputFields;

    public function moneyOutInfo(){
        $user = auth()->user();
        $userWallet = UserWallet::where('user_id',$user->id)->get()->map(function($data){
                return[
                    'balance' => get_amount($data->balance),
                    'currency' => getUserDefaultCurrencyCode(),
                ];
        })->first();

        $transactions = Transaction::auth()->moneyOut()->latest()->take(5)->get()->map(function($item){
            $statusInfo = [
                "success" =>      1,
                "pending" =>      2,
                "rejected" =>     3,
            ];
            return[
                'id'                    => $item->id,
                'trx'                   => $item->trx_id,
                'gateway_name'          => $item->currency->gateway->name,
                'gateway_currency_name' => $item->currency->name,
                'transaction_type'      => $item->type,
                'request_amount'        => get_amount($item->request_amount, getUserDefaultCurrencyCode()),
                'payable'               => get_amount($item->payable, $item->currency->currency_code),
                'exchange_rate'         => '1 ' . getUserDefaultCurrencyCode().' = '.get_amount(conversionAmountCalculation(1, $item->details->data->base_cur_rate, $item->currency->rate),
                $item->currency->currency_code),
                'total_charge'          => get_amount($item->charge->total_charge ?? 0, $item->currency->currency_code),
                'current_balance'       => get_amount($item->available_balance, getUserDefaultCurrencyCode()),
                'status'                => $item->stringStatus->value,
                'date_time'             => $item->created_at,
                'status_info'           => (object)$statusInfo,
                'rejection_reason'      => $item->reject_reason??"",
            ];
        });

        $gateways = PaymentGateway::where('status', 1)->where('slug', PaymentGatewayConst::money_out_slug())->get()->map(function($gateway){
            $currencies = PaymentGatewayCurrency::where('payment_gateway_id',$gateway->id)->get()->map(function($data){
            $default_rate = getUserDefaultCurrencyRate();
                return[
                    'id'                 => $data->id,
                    'payment_gateway_id' => $data->payment_gateway_id,
                    'type'               => $data->gateway->type,
                    'name'               => $data->name,
                    'alias'              => $data->alias,
                    'currency_code'      => $data->currency_code,
                    'currency_symbol'    => $data->currency_symbol,
                    'image'              => $data->image,
                    'min_limit'          => number_format(conversionAmountCalculation($data->min_limit, $data->rate, $default_rate), 4, '.', ''),
                    'max_limit'          => number_format(conversionAmountCalculation($data->max_limit, $data->rate, $default_rate), 4, '.', ''),
                    'percent_charge'     => number_format($data->percent_charge, 4, '.', ''),
                    'fixed_charge'       => number_format(conversionAmountCalculation($data->fixed_charge, $data->rate, $default_rate), 4, '.', ''),
                    'rate'               => number_format($data->rate, 4, '.', ''),
                    'created_at'         => $data->created_at,
                    'updated_at'         => $data->updated_at,
                ];
            });

            return[
                'id'                   => $gateway->id,
                'name'                 => $gateway->name,
                'image'                => $gateway->image,
                'slug'                 => $gateway->slug,
                'code'                 => $gateway->code,
                'type'                 => $gateway->type,
                'alias'                => $gateway->alias,
                'supported_currencies' => $gateway->supported_currencies,
                'input_fields'         => $gateway->input_fields ?? null,
                'status'               => $gateway->status,
                'currencies'           => $currencies
            ];

        });

        $data =[
            'base_curr'                  => getUserDefaultCurrencyCode(),
            'base_curr_rate'             => getUserDefaultCurrencyRate(),
            'default_image'              => "public/backend/images/default/default.webp",
            "image_path"                 => "public/backend/images/payment-gateways",
            'userWallet'                 => (object) $userWallet,
            'gateways'                   => $gateways,
            'transactions'              => $transactions,
        ];
        $message =  ['success'=>[__('Withdraw Information!')]];


        return ApiResponse::success($message, $data);

    }


    public function moneyOutInsert(Request $request){
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|gt:0',
            'gateway' => 'required'
        ]);

        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return ApiResponse::validation($error);
        }

        $basic_setting = BasicSettings::first();
        $user = auth()->user();

        $userWallet = UserWallet::where('user_id',$user->id)->where('status',1)->first();

        $gate =PaymentGatewayCurrency::whereHas('gateway', function ($gateway) {
            $gateway->where('slug', PaymentGatewayConst::money_out_slug());
            $gateway->where('status', 1);
        })->where('alias',$request->gateway)->first();

        if (!$gate) {
            $error = ['error'=>[__('Invalid Gateway!')]];
            return ApiResponse::error($error);
        }

        $baseCurrency = getUserDefaultCurrency();

        if (!$baseCurrency) {
            $error = ['error'=>[__('Default Currency Not Setup Yet!')]];
            return ApiResponse::error($error);
        }

        $amount = $request->amount;

        $min_limit = conversionAmountCalculation($gate->min_limit, $gate->rate, $baseCurrency->rate);
        $max_limit =  conversionAmountCalculation($gate->max_limit, $gate->rate, $baseCurrency->rate);

        if($amount < $min_limit || $amount > $max_limit) {
            $error = ['error'=>[__('Please follow the transaction limit!')]];
            return ApiResponse::error($error);
        }

        //gateway charge
        $conversion_amount = conversionAmountCalculation($request->amount, $baseCurrency->rate, $gate->rate);
        $fixedCharge       = $gate->fixed_charge;
        $percent_charge    = (($conversion_amount / 100) * $gate->percent_charge);
        $charge            = $fixedCharge + $percent_charge;
        $will_get          = $conversion_amount -  $charge;

        //base_cur_charge
        $baseFixedCharge    = $gate->fixed_charge;
        $basePercent_charge = ($conversion_amount / 100) * $gate->percent_charge;
        $base_total_charge  = $baseFixedCharge + $basePercent_charge;
        $reduceAbleTotal    = $amount;

        if( $reduceAbleTotal > $userWallet->balance){
            $error = ['error'=>[__('Insufficient Balance!')]];
            return ApiResponse::error($error);
        }

        $insertData = [
            'user_id'                => $user->id,
            'gateway_name'           => strtolower($gate->gateway->name),
            'gateway_type'           => $gate->gateway->type,
            'wallet_id'              => $userWallet->id,
            'trx_id'                 => generateTrxString('transactions', 'trx_id', 'WD', 8),
            'amount'                 => $amount,
            'base_cur_charge'        => $base_total_charge,
            'base_cur_rate'          => $baseCurrency->rate,
            'gateway_id'             => $gate->gateway->id,
            'gateway_currency_id'    => $gate->id,
            'gateway_currency'       => strtoupper($gate->currency_code),
            'gateway_percent_charge' => $percent_charge,
            'gateway_fixed_charge'   => $fixedCharge,
            'gateway_charge'         => $charge,
            'sender_currency'        => $baseCurrency,
            'gateway_rate'           => $gate->rate,
            'conversion_amount'      => $conversion_amount,
            'exchange_rate'          => conversionAmountCalculation(1,$baseCurrency->rate,$gate->rate),
            'will_get' => $will_get,
            'payable'  => $reduceAbleTotal,
        ];

        $identifier = generate_unique_string("transactions","trx_id",16);

        $inserted = TemporaryData::create([
            'user_id' => Auth::id(),
            'type'          => PaymentGatewayConst::TYPEMONEYOUT,
            'identifier'    => $identifier,
            'data'          => $insertData,
        ]);

        if($inserted){
            $payment_gateway = PaymentGateway::where('id',$gate->payment_gateway_id)->first();

            $payment_information =[
                'trx'                   => $identifier,
                'gateway_currency_name' => $gate->name,
                'request_amount'        => get_amount($request->amount).' '.getUserDefaultCurrencyCode(),
                'exchange_rate'         => "1".' '.getUserDefaultCurrencyCode().' = '.get_amount(conversionAmountCalculation(1,$baseCurrency->rate,$gate->rate)).' '.$gate->currency_code,
                'conversion_amount'     => get_amount($conversion_amount).' '.$gate->currency_code,
                'total_charge'          => get_amount($charge).' '.$gate->currency_code,
                'will_get'              => get_amount($will_get).' '.$gate->currency_code,
                'payable'               => get_amount($reduceAbleTotal).' '.getUserDefaultCurrencyCode(),
            ];

            if ($gate->gateway->type == "AUTOMATIC") {
                $url = route('api.v1.user.withdraw.automatic.confirmed');
                $data =[
                    'payment_information'  => $payment_information,
                    'gateway_type'          => $payment_gateway->type,
                    'gateway_currency_name' => $gate->name,
                    'alias'                 => $gate->alias,
                    'details'               => $payment_gateway->desc??null,
                    'input_fields'          => $payment_gateway->input_fields??null,
                    'url'                   => $url??'',
                    'method'                => "post",
                ];

            }else {

                $url = route('api.v1.user.withdraw.manual.confirmed');
                $data =[
                    'payment_information'  => $payment_information,
                    'gateway_type'          => $payment_gateway->type,
                    'gateway_currency_name' => $gate->name,
                    'alias'                 => $gate->alias,
                    'details'               => $payment_gateway->desc??null,
                    'input_fields'          => $payment_gateway->input_fields??null,
                    'url'                   => $url??'',
                    'method'                => "post",
                ];
            }



            $message =  ['success'=>[__('Withdraw Money Inserted Successfully')]];
            return ApiResponse::success($message, $data);

        }else{
            $error = ['error'=>[__('Something is wrong!')]];
            return ApiResponse::error($error);
        }
    }


    //manual confirmed
    public function moneyOutConfirmed(Request $request){
        $validator = Validator::make($request->all(), [
            'trx'  => "required",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return ApiResponse::validation($error);
        }
        $track = TemporaryData::where('identifier',$request->trx)->where('type',PaymentGatewayConst::TYPEMONEYOUT)->first();
        if(!$track){
            $error = ['error'=>[__('Sorry, your payment information is invalid')]];
            return ApiResponse::error($error);

        }
        $moneyOutData =  $track->data;
        $gateway = PaymentGateway::where('id', $moneyOutData->gateway_id)->first();
        if($gateway->type != "MANUAL"){
            $error = ['error'=>[__('Invalid request, it is not manual gateway request')]];
            return ApiResponse::error($error);
        }
        $payment_fields = $gateway->input_fields ?? [];
        $validation_rules = $this->generateValidationRules($payment_fields);
        $validator2 = Validator::make($request->all(), $validation_rules);
        if ($validator2->fails()) {
            $message =  ['error' => $validator2->errors()->all()];
            return ApiResponse::error($message);
        }
        $validated = $validator2->validate();
        $get_values = $this->placeValueWithFields($payment_fields, $validated);
            try{
                $inserted_id = $this->insertRecordManual($moneyOutData,$gateway,$get_values);
                $this->insertChargesManual($moneyOutData,$inserted_id);
                $this->insertDeviceManual($moneyOutData,$inserted_id);
                $track->delete();
                $message =  ['success'=>[__('Withdraw money request send to admin successfully')]];
                return ApiResponse::onlySuccess($message);
            }catch(Exception $e) {
                $error = ['error'=>[$e->getMessage()]];
                return ApiResponse::error($error);
            }

    }


    //automatic confirmed
    public function confirmMoneyOutAutomatic(Request $request){
        $validator = Validator::make($request->all(), [
            'trx'  => "required",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return ApiResponse::validation($error);
        }
        $track = TemporaryData::where('identifier',$request->trx)->where('type',PaymentGatewayConst::TYPEMONEYOUT)->first();
        if(!$track){
            $error = ['error'=>[__("Sorry, your payment information is invalid")]];
            return ApiResponse::error($error);
        }
        $gateway = PaymentGateway::where('id', $track->data->gateway_id)->first();
        if($gateway->type != "AUTOMATIC"){
            $error = ['error'=>[__("Invalid request, it is not automatic gateway request")]];
            return ApiResponse::error($error);
        }
        //flutterwave automatic
        if($track->data->gateway_name == "flutterwave"){
            $validator = Validator::make($request->all(), [
                'bank_name' => 'required',
                'account_number' => 'required'
            ]);
            if($validator->fails()){
                $error =  ['error'=>$validator->errors()->all()];
                return ApiResponse::validation($error);
            }

            return $this->flutterwavePay($gateway,$request,$track);


        }else{
            $error = ['error'=>[__("Something went wrong! Please try again.")]];
            return ApiResponse::error($error);
        }

    }


    //fluttrwave
    public function flutterwavePay($gateway,$request, $track){
        $moneyOutData =  $track->data;
        $basic_setting = BasicSettings::first();
        $credentials = $gateway->credentials;
        $data = null;
        $secret_key = getPaymentCredentials($credentials,'Secret key');
        $base_url = getPaymentCredentials($credentials,'Base Url');
        $callback_url = url('/').'/flutterwave/withdraw_webhooks';

        $ch = curl_init();
        $url =  $base_url.'/transfers';
        $reference =  generateTransactionReference();
        $data = [
            "account_bank" => $request->bank_name,
            "account_number" => $request->account_number,
            "amount" => $moneyOutData->will_get,
            "narration" => "Withdraw from wallet",
            "currency" => $moneyOutData->gateway_currency,
            "reference" => $reference,
            "callback_url" => $callback_url,
            "debit_currency" => $moneyOutData->gateway_currency
        ];
        $headers = [
            'Authorization: Bearer '.$secret_key,
            'Content-Type: application/json'
        ];
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return back()->with(['error' => [curl_error($ch)]]);
        } else {
            $result = json_decode($response,true);
            if($result['status'] && $result['status'] == 'success'){
                try{
                    $user = auth()->user();
                    $inserted_id = $this->insertRecordManual($moneyOutData,$gateway,$get_values = null,$reference,PaymentGatewayConst::STATUSWAITING);
                    $this->insertChargesAutomatic($moneyOutData,$inserted_id);
                    $this->insertDeviceManual($moneyOutData,$inserted_id);
                    $track->delete();
                    //send notifications

                    try{
                        if( $basic_setting->email_notification == true){
                            $user->notify(new MoneyoutMail($user,$moneyOutData));
                        }
                    }catch(Exception $e){

                    }
                    $message =  ['success'=>[__('Withdraw money request send successful')]];
                    return ApiResponse::onlysuccess($message);
                }catch(Exception $e) {
                    $error = ['error'=>[__("Something went wrong! Please try again.")]];
                    return ApiResponse::error($error);
                }

            }else{
                $error = ['error'=>[$result['message']]];
                return ApiResponse::error($error);
            }
        }

        curl_close($ch);

    }



    public function insertRecordManual($moneyOutData,$gateway,$get_values) {
        if($moneyOutData->gateway_type == "AUTOMATIC"){
            $status = 1;
        }else{
            $status = 2;
        }
        $trx_id = $moneyOutData->trx_id ?? generateTrxString('transactions', 'trx_id', 'WD', 8);
        $authWallet = UserWallet::where('id',$moneyOutData->wallet_id)->where('user_id',$moneyOutData->user_id)->first();
        $afterCharge = ($authWallet->balance - $moneyOutData->amount);
        DB::beginTransaction();
        $data = [
            'get_values' => $get_values,
            'data' => $moneyOutData,
        ];
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => auth()->user()->id,
                'user_wallet_id'                => $moneyOutData->wallet_id,
                'payment_gateway_currency_id'   => $moneyOutData->gateway_currency_id,
                'type'                          => PaymentGatewayConst::TYPEMONEYOUT,
                'trx_id'                        => $trx_id,
                'request_amount'                => $moneyOutData->amount,
                'payable'                       => $moneyOutData->will_get,
                'available_balance'             => $afterCharge,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::TYPEMONEYOUT," ")) . " by " .$gateway->name,
                'details'                       => json_encode($data),
                'status'                        => $status,
                'created_at'                    => now(),
            ]);
            $this->updateWalletBalanceManual($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }

    public function insertChargesAutomatic($moneyOutData,$id) {

        if(Auth::guard(get_auth_guard())->check()){
            $user = auth()->guard(get_auth_guard())->user();
        }
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $moneyOutData->gateway_percent_charge,
                'fixed_charge'      => $moneyOutData->gateway_fixed_charge,
                'total_charge'      => $moneyOutData->gateway_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         => "Withdraw",
                'message'       => "Your Withdraw Request  " .$moneyOutData->amount.' '.$moneyOutData->gateway_currency." Successful",
                'image'         => get_image($user->image,'user-profile'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::MONEY_OUT,
                'user_id'  =>  auth()->user()->id,
                'message'   => $notification_content,
            ]);
            DB::commit();

            //admin notification
            $notification_content['title'] = 'Withdraw Request '.$moneyOutData->amount.' '.$moneyOutData->gateway_currency.' By '.$moneyOutData->gateway_name.' '.$moneyOutData->gateway_currency.' Successful ('.$user->username.')';
            AdminNotification::create([
                'type'      => NotificationConst::MONEY_OUT,
                'admin_id'  => 1,
                'message'   => $notification_content,
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function updateWalletBalanceManual($authWalle,$afterCharge) {
        $authWalle->update([
            'balance'   => $afterCharge,
        ]);
    }

    public function insertChargesManual($moneyOutData,$id) {
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $moneyOutData->gateway_percent_charge,
                'fixed_charge'      => $moneyOutData->gateway_fixed_charge,
                'total_charge'      => $moneyOutData->gateway_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         => "Money Out",
                'message'       => "Your Money Out request send to admin " .$moneyOutData->amount.' '.get_default_currency_code()." successful",
                'image'         => files_asset_path('profile-default'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::MONEY_OUT,
                'user_id'  =>  auth()->user()->id,
                'message'   => $notification_content,
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }

    public function insertDeviceManual($output,$id) {
        $client_ip = request()->ip() ?? false;
        $location = geoip()->getLocation($client_ip);
        $agent = new Agent();
        $mac = "";
        DB::beginTransaction();
        try{
            DB::table("transaction_devices")->insert([
                'transaction_id'=> $id,
                'ip'            => $client_ip,
                'mac'           => $mac,
                'city'          => $location['city'] ?? "",
                'country'       => $location['country'] ?? "",
                'longitude'     => $location['lon'] ?? "",
                'latitude'      => $location['lat'] ?? "",
                'timezone'      => $location['timezone'] ?? "",
                'browser'       => $agent->browser() ?? "",
                'os'            => $agent->platform() ?? "",
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }



    //get flutterwave banks
    public function getBanks(){
        $validator = Validator::make(request()->all(), [
            'trx'  => "required",
        ]);
        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return ApiResponse::validation($error);
        }
        $track = TemporaryData::where('identifier',request()->trx)->where('type',PaymentGatewayConst::TYPEMONEYOUT)->first();
        if(!$track){
            $error = ['error'=>[__("Sorry, your payment information is invalid")]];
            return ApiResponse::error($error);
        }
        if($track['data']->gateway_name != "flutterwave"){
            $error = ['error'=>[__("Sorry, This Payment Request Is Not For FlutterWave")]];
            return ApiResponse::error($error);
        }
        $countries = get_all_countries();
        $currency = $track['data']->gateway_currency;
        $country = Collection::make($countries)->first(function ($item) use ($currency) {
            return $item->currency_code === $currency;
        });

        $allBanks = getFlutterwaveBanks($country->iso2);

        $data =[
            'bank_info' =>$allBanks??[]
        ];
        $message =  ['success'=>[__("All Bank Fetch Successfully")]];
        return ApiResponse::success($data, $message);

    }
}
