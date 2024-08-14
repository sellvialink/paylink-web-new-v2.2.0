<?php

namespace App\Http\Controllers\User;

use Exception;
use App\Models\Product;
use App\Models\UserWallet;
use App\Models\ProductLink;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Http\Helpers\Response;
use App\Models\Admin\Currency;
use App\Models\Admin\GatewayAPi;
use App\Models\Admin\ExchangeRate;
use App\Http\Controllers\Controller;
use App\Models\Admin\PaymentGateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\TransactionSetting;
use Illuminate\Support\Facades\Validator;
use App\Traits\PaymentGateway\StripeLinkPayment;
use App\Http\Helpers\PaymentGateway as PaymentGatewayHelper;

class ProductLinkController extends Controller
{
    use StripeLinkPayment;
    /**
     * Product link page show
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function index($product_id){
        $page_title = __('Product Link');
        $product = Product::findOrFail($product_id);
        if($product->status == 2) return back()->with(['error' => ['Currently, Your Product Is Inactive!']]);
        $product_links = ProductLink::where('product_id',$product_id)->latest()->paginate(12);
        return view('user.sections.product-link.index', compact('page_title','product_links','product_id'));
    }

    /**
     * Product link create page show
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function create($product_id){
        $page_title = __('Product Link Create');
        $currency_data = Currency::active()->get();
        $product = Product::findOrFail($product_id);
        $payment_gateways = PaymentGateway::active()->addMoney()->automatic()->get();
        return view('user.sections.product-link.create', compact('page_title','currency_data','payment_gateways','product'));
    }


     /**
     * Product link store
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function store(Request $request){

        $token = generate_unique_string('product_links', 'token', 60);

        $validator = Validator::make($request->all(), [
            'currency'   => 'required|exists:currencies,id',
            'product_id' => 'required',
            'price'      => 'required|numeric|min:1',
            'quantity'   => 'required|numeric|min:1',
        ]);

        if($validator->stopOnFirstFailure()->fails()){
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $currency = Currency::find($validated['currency']);

        if(empty($currency)){
            return back()->with(['error' => [__('Currency Not Found!')]]);
        }

        $validated['currency']        = $currency->code;
        $validated['currency_symbol'] = $currency->symbol;
        $validated['currency_name']   = $currency->name;
        $validated['country']         = $currency->country;
        $validated['qty'] = $validated['quantity'];

        $validated['token'] = $token;
        $validated['status'] = 1;
        $validated['user_id'] = Auth::id();

        try {
            $product_link = ProductLink::create($validated);
        } catch (\Exception $th) {
            return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
        }

        return redirect()->route('user.product-link.share', $product_link->id)->with(['success' => [__('Product Link Created Successful')]]);
    }

    /**
     * Product link edit page show
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function edit($id){
        $page_title = __('Product Link Edit');
        $currency_data = Currency::active()->get();
        $product_link = ProductLink::findOrFail($id);
        $product = Product::findOrFail($product_link->product_id);
        $payment_gateways = PaymentGateway::active()->addMoney()->automatic()->get();
        return view('user.sections.product-link.edit', compact('page_title','currency_data','payment_gateways','product','product_link'));
    }

    /**
     * Product link update
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function update(Request $request){

        $validator = Validator::make($request->all(), [
            'currency' => 'required|exists:currencies,id',
            'target'   => 'required|exists:product_links,id',
            'price'    => 'required|numeric|min:0.1',
            'quantity' => 'required|numeric|min:1',
        ]);

        if($validator->stopOnFirstFailure()->fails()){
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $currency = Currency::find($validated['currency']);

        if(empty($currency)){
            return back()->with(['error' => [__('Currency Not Found!')]]);
        }

        $validated['currency']        = $currency->code;
        $validated['currency_symbol'] = $currency->symbol;
        $validated['currency_name']   = $currency->name;
        $validated['country']         = $currency->country;
        $validated['qty']             = $validated['quantity'];

        try {
            $product_link = ProductLink::find($validated['target']);
            $product_link->update($validated);
        } catch (\Exception $th) {
            return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
        }

        return redirect()->route('user.product-link.index', $product_link->product_id)->with(['success' => [__('Product Link Updated Successful')]]);
    }

    /**
     * Update Currency Status
     */
    public function statusUpdate(Request $request) {
        $validator = Validator::make($request->all(),[
            'status'                    => 'required',
            'data_target'               => 'required|string',
        ]);
        if ($validator->stopOnFirstFailure()->fails()) {
            $error = ['error' => $validator->errors()];
            return Response::error($error,null,400);
        }
        $validated = $validator->safe()->all();


        $product_id = $validated['data_target'];

        $product = ProductLink::find($product_id);
        if(!$product) {
            $error = ['error' => [__('Product Link record not found in our system.')]];
            return Response::error($error,null,404);
        }

        try{
            $product->update([
                'status' => $validated['status'],
            ]);
        }catch(Exception $e) {
            $error = ['error' => [__('Something went wrong! Please try again.')]];
            return Response::error($error,null,500);
        }

        $success = ['success' => [__('Product Link status updated successfully!')]];
        return Response::success($success,null,200);
    }

    /**
     * Product Link Share
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function share($id){
        $page_title = __('Product Link Share');
        $product_link = ProductLink::findOrFail($id);
        return view('user.sections.product-link.share', compact('page_title','product_link'));
    }

    /**
     * Product Link Share
     *
     * @method GET
     * @return Illuminate\Http\Request
     */

     public function productLinkShare($token){
        $product_link = ProductLink::with('user','product')->where('token', $token)->first();
        if(empty($product_link)){
            return redirect()->route('index')->with(['error' => [__('Invalid Payment Link')]]);
        }

        if($product_link->status == 2 || $product_link->product->status == 2) return redirect()->route('index')->with(['error' => ['Currently, The Service Is  Inactive By Owner']]);

        $payment_gateways = PaymentGateway::active()->addMoney()->automatic()->get();

        $credentials = GatewayAPi::first();
        if(empty($credentials)){
            return redirect()->route('index')->with(['error' => [__('Can Not Payment Now, Please Contact Support')]]);
        }
        $public_key = $credentials->public_key;

        $page_title = __('Product Payment Link');
        return view('frontend.product-link.share', compact('product_link', 'page_title', 'public_key', 'payment_gateways'));
    }

    /**
     * Product Link Submit
     *
     * @param @return Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */

    public function productLinkSubmit(Request $request){

        $validator = Validator::make($request->all(), [
            'target'          => 'required',
            'payment_type'    => 'required|string'
        ]);

        if($validator->fails()){
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $payment_method = [
            PaymentGatewayConst::TYPE_GATEWAY_PAYMENT => 'gatewayPaymentRequest',
            PaymentGatewayConst::TYPE_CARD_PAYMENT => 'cardPaymentRequest',
        ];

        if(!array_key_exists($validated['payment_type'], $payment_method)) return abort(404);
        $method = $payment_method[$validated['payment_type']];
        return $this->$method($request);

    }

     /**
     * Card Payment Request
     *
     * @method GET
     * @return Illuminate\Http\Request
     */

     public function cardPaymentRequest(Request $request){

        $validator = Validator::make($request->all(),[
            'target'     => 'required',
            'email'      => 'required|email',
            'full_name'  => 'required|string',
            'card_name'  => 'required',
            'token'      => 'required',
            'last4_card' => 'required',
            'amount'     => 'required',
        ]);

        if($validator->fails()){
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $credentials = GatewayAPi::first();

        if(empty($credentials)){
            return back()->with(['error' => [__('Transaction Failed, Please Contact Support!')]]);
        }

        $product_link = ProductLink::with('user')->find($validated['target']);

        if(empty($product_link)){
            return back()->with(['error' => [__('Invalid Request!')]]);
        }

        $amount = $product_link->price * $product_link->qty;
        $validated['product_link'] = $product_link;

        $receiver_currency = ExchangeRate::where('name', $product_link->user->address->country)->first();
        if(empty($receiver_currency)){
            return back()->with(['error' => [__('Receiver currency not found!')]]);
        }

        $receiver_wallet = UserWallet::with('user','currency')->where('user_id', $product_link->user_id)->first();
        if(empty($receiver_wallet)){
            return back()->with(['error' => [__('User wallet not found!')]]);
        }

        $sender_currency = ExchangeRate::where('currency_code', $product_link->currency)->where('currency_name', $product_link->currency_name)->first();

        $validated['receiver_wallet'] = $receiver_wallet;
        $validated['sender_currency'] = $sender_currency;
        $validated['transaction_type'] = PaymentGatewayConst::TYPEPRODUCT;

        $product_link_charge = TransactionSetting::where('slug', PaymentGatewayConst::paylink_slug())->where('status',1)->first();

        $fixedCharge        = $product_link_charge->fixed_charge * $sender_currency->rate;
        $percent_charge     = ($amount / 100) * $product_link_charge->percent_charge;
        $total_charge       = $fixedCharge + $percent_charge;
        $payable            = $amount - $total_charge;

        if($payable <= 0 ){
            return back()->with(['error' => [__('Transaction Failed, Please Contact With Support!')]]);
        }

        $conversion_charge  = conversionAmountCalculation($total_charge, $sender_currency->rate, $receiver_currency->rate);
        $conversion_payable = conversionAmountCalculation($payable, $sender_currency->rate ,$receiver_currency->rate);
        $total_conversion   = conversionAmountCalculation($amount, $sender_currency->rate ,$receiver_currency->rate);
        $exchange_rate      = conversionAmountCalculation(1, $receiver_currency->rate, $sender_currency->rate);
        $conversion_admin_charge = $total_charge / $sender_currency->rate;

        $charge_calculation = [
            'requested_amount'       => $amount,
            'request_amount_admin'   => $amount / $sender_currency->rate,
            'fixed_charge'           => $fixedCharge,
            'percent_charge'         => $percent_charge,
            'total_charge'           => $total_charge,
            'conversion_charge'      => $conversion_charge,
            'conversion_admin_charge'=> $conversion_admin_charge,
            'payable'                => $payable,
            'conversion_payable'     => $conversion_payable,
            'exchange_rate'          => $exchange_rate,
            'sender_cur_code'        => $product_link->currency,
            'receiver_currency_code' => $receiver_currency->currency_code,
            'base_currency_code'     => get_default_currency_code(),
        ];

        $validated['charge_calculation'] = $charge_calculation;

       try {
            $this->stripeInit($validated, $credentials);
            return redirect()->route('product-link.transaction.success', $product_link->token)->with(['success' => [__('Transaction Successful')]]);
       } catch (\Exception $e) {
            return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
       }
    }

     /**
     * Gateway Payment Request
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function gatewayPaymentRequest(Request $request){

        $validator = Validator::make($request->all(),[
            'target'          => 'required',
            'amount'          => 'required',
            'email'           => 'required|email',
            'full_name'       => 'required|string',
            'payment_gateway' => 'required|exists:payment_gateways,alias',
        ]);

        if($validator->fails()) return back()->withErrors($validator)->withInput();
        $validated = $validator->validated();

        $product_link = ProductLink::with('user','product')->find($validated['target']);
        if(empty($product_link)) return back()->with(['error' => [__('Invalid Request!')]]);

        $validated['amount'] = $product_link->amount_value;

        $payment_gateway = PaymentGateway::where('alias', $validated['payment_gateway'])->withWhereHas('currency',function($q) use ($product_link){
            $q->where("currency_code",$product_link->currency);
        })->first();

        if(!$payment_gateway) return back()->with(['error' => [__('Gateway Currency Is Not Supported!')]]);

        $request->merge(['currency' => $payment_gateway->currency->alias]);

       try {
            $instance = PaymentGatewayHelper::init($request->all())->type(PaymentGatewayConst::TYPEPRODUCT)->gateway()->render();
       } catch (\Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
       }

       return $instance;
    }

    /**
     * Transaction Success
     *
     * @method GET
     * @return Illuminate\Http\Request
     */

    public function transactionSuccess($token){
        $product_link = ProductLink::with('user')->where('token', $token)->first();
        $page_title = __('Payment Success');
        return view('frontend.product-link.transaction-success', compact('product_link', 'page_title'));
    }

    /**
     * This method for success alert of PayPal
     * @method POST
     * @param Illuminate\Http\Request $request
     * @return Illuminate\Http\Request
     */
    public function paypalSuccess(Request $request, $gateway){
        $requestData = $request->all();
        $token = $requestData['token'] ?? "";
        $checkTempData = TemporaryData::where("type",$gateway)->where("identifier",$token)->first();
        if(!$checkTempData) return redirect()->route('index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again.')]]);
        $checkTempData = $checkTempData->toArray();
        try{
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEPRODUCT)->responseReceive();
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }

        $product_link = ProductLink::find($checkTempData['data']->validated->target);

        return redirect()->route('product-link.transaction.success', $product_link->token)->with(['success' => [__('Transaction Successful')]]);
    }

    /**
     * This method for cancel alert of PayPal
     * @method POST
     * @param Illuminate\Http\Request $request
     * @return Illuminate\Http\Request
     */
    public function paypalCancel(Request $request, $gateway) {
        $token = session()->get('identifier');
        if( $token){
            TemporaryData::where("identifier",$token)->delete();
        }
        return redirect()->route('index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again.')]]);
    }

    /**
     * Stripe Success
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function stripeSuccess($trx){
        $token = $trx;
        $checkTempData = TemporaryData::where("type",PaymentGatewayConst::STRIPE)->where("identifier",$token)->first();
        if(!$checkTempData) return redirect()->route('index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again.')]]);
        $checkTempData = $checkTempData->toArray();
        try{
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEPRODUCT)->responseReceive('stripe');
            $invoice = ProductLink::find($checkTempData['data']->validated->target);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Is Wrong')."..."]]);
        }


       return redirect()->route('product-link.transaction.success', $invoice->token)->with(['success' => [__('Transaction Successful')]]);
    }

    /**
     * This method for success alert of PayPal
     * @method POST
     * @param Illuminate\Http\Request $request
     * @return Illuminate\Http\Request
     */
    public function flutterwaveCallback($token){
        if(request()->status == 'successful'){
            $checkTempData = TemporaryData::where("identifier",$token)->first();
            if(!$checkTempData) return redirect()->route('index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again.')]]);
            $checkTempData = $checkTempData->toArray();
            try{
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEPRODUCT)->responseReceive('flutterwave');
            }catch(Exception $e) {
                return back()->with(['error' => [$e->getMessage()]]);
            }
            $product_link = ProductLink::find($checkTempData['data']->validated->target);
            return redirect()->route('product-link.transaction.success', $product_link->token)->with(['success' => [__('Transaction Successful')]]);
        }else{
            if( $token){
                TemporaryData::where("identifier",$token)->delete();
            }
            return redirect()->route('index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again.')]]);
        }
    }

    /**
     * This method for cancel alert of PayPal
     * @method POST
     * @param Illuminate\Http\Request $request
     * @return Illuminate\Http\Request
     */
    public function flutterwaveCancel($token) {
        if($token){
            TemporaryData::where("identifier",$token)->delete();
        }
        return redirect()->route('index');
    }

    //sslcommerz success
    public function sslCommerzSuccess(Request $request){
        $data = $request->all();
        $token = $data['tran_id'];
        $checkTempData = TemporaryData::where("type",PaymentGatewayConst::SSLCOMMERZ)->where("identifier",$token)->first();
        if(!$checkTempData) return redirect()->route('index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again.')]]);
        $checkTempData = $checkTempData->toArray();
        if( $data['status'] != "VALID"){
            return redirect()->route('index')->with(['error' => [__('Transaction failed')]]);
        }
        try{
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEPRODUCT)->responseReceive('sslcommerz');
        }catch(Exception $e) {
            return back()->with(['error' => ["Something Is Wrong..."]]);
        }

        $product_link = ProductLink::find($checkTempData['data']->validated->target);
        return redirect()->route('product-link.transaction.success', $product_link->token)->with(['success' => [__('Transaction Successful')]]);
    }

    //sslCommerz fails
    public function sslCommerzFails(Request $request){
        $data = $request->all();
        $token = $data['tran_id'];
        $checkTempData = TemporaryData::where("type",PaymentGatewayConst::SSLCOMMERZ)->where("identifier",$token)->first();
        if(!$checkTempData) return redirect()->route('index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again.')]]);
        $checkTempData = $checkTempData->toArray();
        if( $data['status'] == "FAILED"){
            TemporaryData::destroy($checkTempData['id']);
            return redirect()->route('index')->with(['error' => [__('Transaction Failed Failed')]]);
        }

    }

    //sslCommerz canceled
    public function sslCommerzCancel(Request $request){
        $data = $request->all();
        $token = $data['tran_id'];
        $checkTempData = TemporaryData::where("type",PaymentGatewayConst::SSLCOMMERZ)->where("identifier",$token)->first();
        if(!$checkTempData) return redirect()->route('index')->with(['error' => [__('Transaction Failed. Record didn\'t saved properly. Please try again.')]]);
        $checkTempData = $checkTempData->toArray();
        if( $data['status'] != "VALID"){
            TemporaryData::destroy($checkTempData['id']);
            return redirect()->route('index')->with(['error' => [__('Transaction Canceled')]]);
        }
    }

    public function razorCallback()
    {
        $request_data = request()->all();
        //if payment is successful
        if (isset($request_data['razorpay_order_id'])) {
            $token = $request_data['razorpay_order_id'];

            $checkTempData = TemporaryData::where("type",PaymentGatewayConst::RAZORPAY)->where("identifier",$token)->first();
            if(!$checkTempData) return redirect()->route('index')->with(['error' => [__("Transaction Failed. Record didn\'t saved properly. Please try again")]]);
            $checkTempData = $checkTempData->toArray();
            try{
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEPRODUCT)->responseReceive('razorpay');
            }catch(Exception $e) {
                return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
            }

            $product_link = ProductLink::find($checkTempData['data']->validated->target);
            return redirect()->route('product-link.transaction.success', $product_link->token)->with(['success' => [__('Transaction Successful')]]);

        }
        else{
            return redirect()->route('index')->with(['error' => [__("Transaction failed")]]);
        }
    }

    public function razorCancel($trx_id){
        $token = $trx_id;
        if( $token){
            TemporaryData::where("identifier",$token)->delete();
        }
        return redirect()->route('index')->with(['error' => [__('Transaction Cancelled')]]);
    }

     // Qrpay Call Back
     public function qrpayCallback(Request $request)
     {
         if ($request->type ==  'success') {
            $requestData = $request->all();
            $checkTempData = TemporaryData::where("type", 'qrpay')->where("identifier", $requestData['data']['custom'])->first();

            if (!$checkTempData) return redirect()->route('index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again.')]]);
            $checkTempData = $checkTempData->toArray();

            try {
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEPRODUCT)->responseReceive('qrpay');
            } catch (Exception $e) {
                return back()->with(['error' => [$e->getMessage()]]);
            }

            $payment_link = ProductLink::find($checkTempData['data']->validated->target);
            return redirect()->route('product-link.transaction.success', $payment_link->token)->with(['success' => [__('Transaction Successful')]]);
         } else {
             return redirect()->route('index')->with(['error' => [__('Transaction failed')]]);
         }
     }

     // QrPay Cancel
     public function qrpayCancel(Request $request, $trx_id)
     {
         TemporaryData::where("identifier", $trx_id)->delete();
         return redirect()->route("index")->with(['error' => [__('Payment Canceled')]]);
     }

    public function successGlobal(Request $request, $gateway){
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
            $temp_data = TemporaryData::where("identifier",$token)->first();
            $product_link = ProductLink::find($temp_data['data']->validated->target);

            if(Transaction::where('callback_ref', $token)->exists()) {
                if(!$temp_data) return redirect()->route('product-link.transaction.success', $product_link->token)->with(['success' => [__('Transaction Successful')]]);
            }else {
                if(!$temp_data) return redirect()->route('index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
            }

            $update_temp_data = json_decode(json_encode($temp_data->data),true);
            $update_temp_data['callback_data']  = $request->all();
            $temp_data->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $temp_data->toArray();
            $instance = PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEPRODUCT)->responseReceive($temp_data['type']);
            if($instance instanceof RedirectResponse) return $instance;
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
        return redirect()->route('product-link.transaction.success', $product_link->token)->with(['success' => [__('Transaction Successful')]]);
    }

    public function cancelGlobal(Request $request, $gateway) {

        $token = PaymentGatewayHelper::getToken($request->all(),$gateway);

        if($temp_data = TemporaryData::where("identifier",$token)->first()) {
            $temp_data->delete();
        }

        return redirect()->route('index')->with(['error' => [__('Transaction Canceled')]]);
    }

    public function postSuccess(Request $request, $gateway)
    {
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
            $temp_data = TemporaryData::where("identifier",$token)->first();
        }catch(Exception $e) {
            return redirect()->route('index')->with(['error' => [__('Transaction failed')]]);
        }

        return $this->successGlobal($request, $gateway);
    }

    public function postCancel(Request $request, $gateway)
    {
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
            $temp_data = TemporaryData::where("identifier",$token)->first();
            Auth::guard($temp_data->data->creator_guard)->loginUsingId($temp_data->data->creator_id);
        }catch(Exception $e) {
            return redirect()->route('index')->with(['error' => [__('Transaction Canceled')]]);
        }

        return $this->cancelGlobal($request, $gateway);
    }

    //coingate response start
    public function coinGateSuccess(Request $request, $gateway){
        try{
            $token = $request->token;
            $checkTempData = TemporaryData::where("type",PaymentGatewayConst::COIN_GATE)->where("identifier",$token)->first();
            $product_link = ProductLink::find($checkTempData['data']->validated->target);

            if(!$checkTempData) return redirect()->route('index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);

            if(Transaction::where('callback_ref', $token)->exists()) {
                if(!$checkTempData) return redirect()->route('product-link.transaction.success', $product_link->token)->with(['success' => [__('Transaction Successful')]]);
            }else {
                if(!$checkTempData) return redirect()->route('index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
            }

            $update_temp_data = json_decode(json_encode($checkTempData->data),true);
            $update_temp_data['callback_data']  = $request->all();

            $checkTempData->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $checkTempData->toArray();
            PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEPRODUCT)->responseReceive('coingate');
        }catch(Exception $e) {
            return redirect()->route("index")->with(['error' => [__('Something went wrong! Please try again.')]]);
        }

        return redirect()->route('product-link.transaction.success', $product_link->token)->with(['success' => [__('Transaction Successful')]]);
    }

    public function coinGateCancel(Request $request, $gateway){
        if($request->has('token')) {
            $identifier = $request->token;
            if($temp_data = TemporaryData::where('identifier', $identifier)->first()) {
                $temp_data->delete();
            }
        }
        return redirect()->route("index")->with(['error' => [__('Transaction Cancelled')]]);
    }

    /**
     * This method for success alert of Payment Gateway
     * @method POST
     * @param Illuminate\Http\Request $request
     * @return Illuminate\Http\Request
     */
    public function coingateCallback(Request $request,$gateway) {
        $callback_token = $request->get('token');
        $callback_data = $request->all();
        try{
            PaymentGatewayHelper::init([])->type(PaymentGatewayConst::TYPEPRODUCT)->handleCallback($callback_token,$callback_data,$gateway);
        }catch(Exception $e) {
            // handle Error
            logger($e);
        }
    }

    public function redirectUsingHTMLForm(Request $request, $gateway)
    {
        $temp_data = TemporaryData::where('identifier', $request->token)->first();
        if(!$temp_data || $temp_data->data->action_type != PaymentGatewayConst::REDIRECT_USING_HTML_FORM) return back()->with(['error' => ['Request token is invalid!']]);
        $redirect_form_data = $temp_data->data->redirect_form_data;
        $action_url         = $temp_data->data->action_url;
        $form_method        = $temp_data->data->form_method;

        return view('user.payment-gateway.redirect-form', compact('redirect_form_data', 'action_url', 'form_method'));
    }


    public function perfectSuccess(Request $request, $gateway){
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
            $temp_data = TemporaryData::where("type",PaymentGatewayConst::PERFECT_MONEY)->where("identifier",$token)->first();
            $payment_link = ProductLink::find($temp_data['data']->validated->target);

            if(Transaction::where('callback_ref', $token)->exists()) {
                if(!$temp_data) return redirect()->route('product-link.transaction.success', $payment_link->token)->with(['success' => [__('Transaction Successful')]]);
            }else {
                if(!$temp_data) return redirect()->route('index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
            }

            $update_temp_data = json_decode(json_encode($temp_data->data),true);
            $update_temp_data['callback_data']  = $request->all();
            $temp_data->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $temp_data->toArray();
            $instance = PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEPRODUCT)->responseReceive('perfect-money');
            if($instance instanceof RedirectResponse) return $instance;
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }

        return redirect()->route('product-link.transaction.success', $payment_link->token)->with(['success' => [__('Transaction Successful')]]);
    }

    public function perfectCancel(Request $request, $gateway) {

        if($request->has('token')) {
            $identifier = $request->token;
            if($temp_data = TemporaryData::where('identifier', $identifier)->first()) {
                $temp_data->delete();
            }
        }

        return redirect()->route("index")->with(['error' => [__('Transaction Cancelled')]]);
    }

    public function perfectCallback(Request $request,$gateway) {

        $callback_token = $request->get('token');
        $callback_data = $request->all();
        try{
            PaymentGatewayHelper::init([])->type(PaymentGatewayConst::TYPEPRODUCT)->handleCallback($callback_token,$callback_data,$gateway);
        }catch(Exception $e) {
            // handle Error
            logger($e);
        }
    }

    public function delete(Request $request) {
        $validator = Validator::make($request->all(),[
            'target'        => 'required|string|exists:product_links,id',
        ]);
        $validated = $validator->validate();
        $product_link = ProductLink::find($validated['target']);

        try{
            $product_link->delete();
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Product Link deleted successfully!')]]);
    }


}
