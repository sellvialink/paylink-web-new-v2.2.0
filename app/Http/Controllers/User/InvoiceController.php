<?php

namespace App\Http\Controllers\User;

use Exception;
use App\Models\UserWallet;
use App\Models\Transaction;
use App\Models\User\Invoice;
use Illuminate\Http\Request;
use App\Models\TemporaryData;
use App\Models\Admin\Currency;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Admin\GatewayAPi;
use App\Models\User\InvoiceItem;
use App\Models\Admin\ExchangeRate;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Http\Controllers\Controller;
use App\Models\Admin\PaymentGateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\TransactionSetting;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;
use App\Notifications\User\InvoiceNotification;
use App\Traits\PaymentGateway\StripeLinkPayment;
use App\Http\Helpers\PaymentGateway as PaymentGatewayHelper;

class InvoiceController extends Controller
{
    use StripeLinkPayment;

     /**
     * Invoice link page show
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function index(){
        $page_title = __('Invoice');
        $invoices = Invoice::auth()->with('invoiceItems')->where('user_id', Auth::id())->orderBy('id', 'desc')->paginate(12);
        return view('user.sections.invoice.index', compact('page_title','invoices'));
    }


    /**
     * Invoice link create page show
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function create(){
        $page_title = __('Invoice Create');
        $currency_data = Currency::active()->get();
        $invoice_no = 'INV-'.generate_unique_string('invoices', 'invoice_no', 12, 'upper');
        return view('user.sections.invoice.create', compact('page_title','currency_data','invoice_no'));
    }


    /**
     * Invoice link store
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function store(Request $request){

        $token = generate_unique_string('payment_links', 'token', 60);

        $validator = Validator::make($request->all(), [
            'currency'        => 'required',
            'currency_symbol' => 'required',
            'country'         => 'required',
            'currency_name'   => 'required',
            'invoice_no'      => 'required',
            'title'           => 'required',
            'name'            => 'required|max:120',
            'email'           => 'required|email',
            'phone'           => 'required',
            'total_qty'       => 'required',
            'total_price'     => 'required',
            'item_title.*'    => 'required',
            'item_qty.*'      => 'required',
            'item_price.*'    => 'required',
        ]);

        if($validator->fails()){
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();


        $validated['token'] = $token;
        $validated['status'] = 3;
        $validated['user_id'] = Auth::id();
        $validated['qty'] = $validated['total_qty'];
        $validated['amount'] = $validated['total_price'];

        DB::beginTransaction();
        try {

            $invoice = Invoice::create($validated);

            $invoice_item = [];
            foreach ($validated['item_title'] as $key => $value) {
                $invoice_item[] = [
                    'invoice_item_id' => $invoice->id,
                    'title'           => $value,
                    'qty'             => $validated['item_qty'][$key],
                    'price'           => $validated['item_price'][$key],
                    'created_at'      => Carbon::now(),
                    'updated_at'      => Carbon::now(),
                ];
            }

            InvoiceItem::insert($invoice_item);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
        }

        return redirect()->route('user.invoice.preview', $invoice->id)->with(['success' => [__('Invoice Created Successful')]]);
    }


    /**
     * Invoice Edit page show
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function edit($id){
        $page_title = __('Payment Link Create');
        $currency_data = Currency::active()->get();
        $invoice = Invoice::with('invoiceItems')->findOrFail($id);
        return view('user.sections.invoice.edit', compact('page_title','currency_data','invoice'));
    }

     /**
     * Invoice link store
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function update(Request $request){

        $validator = Validator::make($request->all(), [
            'currency'        => 'required',
            'currency_symbol' => 'required',
            'country'         => 'required',
            'currency_name'   => 'required',
            'title'           => 'required',
            'name'            => 'required|max:120',
            'email'           => 'required|email',
            'phone'           => 'required',
            'total_qty'       => 'required',
            'total_price'     => 'required',
            'item_title.*'    => 'required',
            'item_qty.*'      => 'required',
            'item_price.*'    => 'required',
            'target'          => 'required',
        ]);

        if($validator->fails()){
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();;

        $validated['status'] = 3;
        $validated['user_id'] = Auth::id();
        $validated['qty'] = $validated['total_qty'];
        $validated['amount'] = $validated['total_price'];

        DB::beginTransaction();
        try {

            $invoice = Invoice::findOrFail($validated['target'])->update($validated);

            InvoiceItem::where('invoice_item_id', $validated['target'])->delete();

            $invoice_item = [];
            foreach ($validated['item_title'] as $key => $value) {
                $invoice_item[] = [
                    'invoice_item_id' => $validated['target'],
                    'title'           => $value,
                    'qty'             => $validated['item_qty'][$key],
                    'price'           => $validated['item_price'][$key],
                    'created_at'      => Carbon::now(),
                    'updated_at'      => Carbon::now(),
                ];
            }

            InvoiceItem::insert($invoice_item);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
        }

        return redirect()->route('user.invoice.preview', $validated['target'])->with(['success' => [__('Invoice Updated Successful')]]);
    }


    /**
     * Invoice link Share page show
     *
     * @method Preview
     * @return Illuminate\Http\Request
     */
    public function preview($id){
        $page_title = __('Invoice Preview');
        $invoice = Invoice::with('invoiceItems')->findOrFail($id);
        return view('user.sections.invoice.preview', compact('page_title','invoice'));
    }

    /**
     * Invoice link Share page show
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function share($id){
        $page_title = __('Invoice Share');
        $invoice = Invoice::findOrFail($id);
        return view('user.sections.invoice.share', compact('page_title','invoice'));
    }


    /**
     * Invoice link Show
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function show(Request $request){
        $invoice = Invoice::with('invoiceItems')->find($request->target);

        $data = [
            'invoice' => $invoice,
            'date' => dateFormat('d F Y', $invoice->created_at),
            'route' => setRoute('invoice.share', $invoice->token),
            'download_route' => setRoute('user.invoice.pdf.download', $invoice->id),
        ];

        return response()->json($data);
    }


    /**
     * Invoice Link Share
     *
     * @method GET
     * @return Illuminate\Http\Request
     */

     public function invoiceShare($token){

        $invoice = Invoice::where('status', 2)->where('token', $token)->first();

        if(empty($invoice)){
            return redirect()->route('index')->with(['error' => ['Invalid Invoice Link']]);
        }

        $payment_gateways = PaymentGateway::active()->addMoney()->automatic()->get();

        $credentials = GatewayAPi::first();

        if(empty($credentials)){
            return redirect()->route('index')->with(['error' => [__('Can Not Payment Now, Please Contact Support')]]);
        }
        $public_key = $credentials->public_key;

        $page_title = __('Invoice Link');
        return view('frontend.invoice.share', compact('invoice', 'page_title', 'public_key', 'payment_gateways'));
    }

     /**
     * Invoice store
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function status(Request $request){

        $validator = Validator::make($request->all(), [
            'target'        => 'required',
            'status'        => 'required',
        ]);

        if($validator->fails()){
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $invoice = Invoice::find($validated['target']);

        try {
            $invoice->update(['status' => $validated['status']]);

            if($validated['status'] != 3){
                $basic_settings = BasicSettings::first();

                if($basic_settings->email_notification){
                    try {
                        $invoice = Invoice::with('user','invoiceItems')->where('id',$invoice->id)->first();
                        Notification::route('mail', [$invoice->email])->notify(new InvoiceNotification($invoice));
                    } catch (\Exception $e) {
                        //Handle Error
                    }
                }
            }

        } catch (\Exception $th) {
            return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
        }

        if($validated['status'] == 3){
            return redirect()->route('user.invoice.index')->with(['success' => [__('Invoice Save As Draft')]]);
        }else{
            return redirect()->route('user.invoice.share', $invoice->id)->with(['success' => [__('Invoice Published Successful')]]);
        }

    }

    /**
     * Invoice Payment Submit
     *
     * @param @return Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */

    public function invoiceSubmit(Request $request){

        $validator = Validator::make($request->all(),[
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
     * Gateway Payment Request
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function gatewayPaymentRequest(Request $request){

        $validator = Validator::make($request->all(),[
            'target'          => 'required',
            'email'           => 'required|email',
            'full_name'       => 'required|string',
            'payment_gateway' => 'required|exists:payment_gateways,alias',
        ]);

        if($validator->fails()) return back()->withErrors($validator)->withInput();
        $validated = $validator->validated();

        $invoice = Invoice::with('user','invoiceItems')->find($validated['target']);
        if(empty($invoice)) return back()->with(['error' => [__('Invalid Request!')]]);

        $payment_gateway = PaymentGateway::where('alias', $validated['payment_gateway'])->withWhereHas('currency',function($q) use ($invoice){
            $q->where("currency_code",$invoice->currency);
        })->first();

        if(!$payment_gateway) return back()->with(['error' => [__('Gateway Currency Is Not Supported!')]]);

        $request->merge(['currency' => $payment_gateway->currency->alias, 'amount' => $invoice->amount]);

       try {
            $instance = PaymentGatewayHelper::init($request->all())->type(PaymentGatewayConst::TYPEINVOICE)->gateway()->render();
       } catch (\Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
       }

       return $instance;
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
        ]);

        if($validator->fails()){
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $credentials = GatewayAPi::first();
        if(empty($credentials)){
            return back()->with(['error' => [__('Transaction Failed, Please Contact Support!')]]);
        }

        $invoice = Invoice::with('user','invoiceItems')->find($validated['target']);

        $amount = $invoice->amount;
        $validated['invoice'] = $invoice;
        $validated['amount'] = $amount;
        $receiver_currency = ExchangeRate::where('name', $invoice->user->address->country)->first();

        if(empty($receiver_currency)){
            return back()->with(['error' => [__('Receiver currency not found!')]]);
        }

        $receiver_wallet = UserWallet::with('user','currency')->where('user_id', $invoice->user_id)->first();

        if(empty($receiver_wallet)){
            return back()->with(['error' => [__('User wallet not found!')]]);
        }

        $sender_currency = ExchangeRate::where('currency_code', $invoice->currency)->where('currency_name', $invoice->currency_name)->first();

        if(empty($sender_currency)){
            return back()->with(['error' => [__('Sender currency not found!')]]);
        }

        $validated['receiver_wallet']  = $receiver_wallet;
        $validated['sender_currency']  = $sender_currency;
        $validated['transaction_type'] = PaymentGatewayConst::TYPEINVOICE;

        $invoice_charge = TransactionSetting::where('slug', PaymentGatewayConst::paylink_slug())->where('status',1)->first();

        $fixedCharge        = $invoice_charge->fixed_charge * $sender_currency->rate;
        $percent_charge     = ($amount/ 100) * $invoice_charge->percent_charge;
        $total_charge       = $fixedCharge + $percent_charge;
        $payable            = $amount - $total_charge;


        if($payable <= 0 ){
            return back()->with(['error' => [__('Transaction Failed, Please Contact With Support!')]]);
        }


        $conversion_charge  = conversionAmountCalculation($total_charge, $sender_currency->rate, $receiver_currency->rate);
        $conversion_payable = conversionAmountCalculation($payable, $sender_currency->rate ,$receiver_currency->rate);
        $total_conversion = conversionAmountCalculation($amount, $sender_currency->rate ,$receiver_currency->rate);
        $exchange_rate      = conversionAmountCalculation(1, $receiver_currency->rate, $sender_currency->rate);
        $conversion_admin_charge = $total_charge / $sender_currency->rate;

        $charge_calculation = [
            'requested_amount'       => $amount,
            'request_amount_admin'   => $amount / $sender_currency->rate,
            'fixed_charge'           => $fixedCharge,
            'percent_charge'         => $percent_charge,
            'total_charge'           => $total_charge,
            'conversion_charge'      => $conversion_charge,
            'conversion_admin_charge' => $conversion_admin_charge,
            'payable'                => $payable,
            'conversion_payable'     => $conversion_payable,
            'exchange_rate'          => $exchange_rate,
            'sender_cur_code'        => $invoice->currency,
            'receiver_currency_code' => $receiver_currency->currency_code,
            'base_currency_code'     => get_default_currency_code(),
        ];

        $validated['charge_calculation'] = $charge_calculation;

       try {
            $this->stripeInit($validated, $credentials);
            $invoice->update(['status' => 1]);
        } catch (\Exception $e) {
            return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
        }

        return redirect()->route('invoice.transaction.success', $invoice->token)->with(['success' => [__('Transaction Successful')]]);

    }

    /**
     * Invoice Delete
     *
     * @param @return Illuminate\Http\Request $request
     * @method DELETE
     * @return Illuminate\Http\Request
     */

    public function delete(Request $request){
        $validator = Validator::make($request->all(),[
            'target'     => 'required',
        ]);

        $validated = $validator->validated();

        if($validator->fails()){
            return back()->withErrors($validator)->withInput();
        }

        try {
            Invoice::findOrFail($validated['target'])->delete();
        } catch (\Exception $e) {
            return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
        }

        return back()->with(['success' => [__('Invoice Deleted Successful')]]);
    }

    /**
     * PDF Download
     *
     * @param @return Illuminate\Http\Request $request
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function downloadPdf($id){
        $invoice = Invoice::with('invoiceItems')->findOrFail($id);
        $data = [
            'invoice' => $invoice,
        ];
        $pdf = Pdf::loadView('user.sections.invoice.pdf-generate', $data)->setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
        $pdf_download_name =  $invoice->invoice_no ?? now()->format("d-m-Y H:i");
        return $pdf->download($pdf_download_name.".pdf");
    }


    /**
     * Transaction Success
     *
     * @method GET
     * @return Illuminate\Http\Request
     */

     public function transactionSuccess($token){
         $invoice = Invoice::with('user')->where('token', $token)->first();
        $page_title = __('Payment Success');
        return view('frontend.invoice.transaction-success', compact('invoice', 'page_title'));
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
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEINVOICE)->responseReceive('stripe');
            $invoice = Invoice::find($checkTempData['data']->validated->target);
            $invoice->update(['status' => 1]);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Is Wrong')."..."]]);
        }


       return redirect()->route('invoice.transaction.success', $invoice->token)->with(['success' => [__('Transaction Successful')]]);
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
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEINVOICE)->responseReceive();
            $invoice = Invoice::find($checkTempData['data']->validated->target);
            $invoice->update(['status' => 1]);
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }

       return redirect()->route('invoice.transaction.success', $invoice->token)->with(['success' => [__('Transaction Successful')]]);
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
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEINVOICE)->responseReceive('flutterwave');
            }catch(Exception $e) {
                return back()->with(['error' => [$e->getMessage()]]);
            }
            $invoice = Invoice::find($checkTempData['data']->validated->target);
            $invoice->update(['status' => 1]);
            return redirect()->route('invoice.transaction.success', $invoice->token)->with(['success' => [__('Transaction Successful')]]);
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
            return redirect()->route('index')->with(['error' => [__('Transaction Failed')]]);
        }
        try{
            PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEINVOICE)->responseReceive('sslcommerz');
            $invoice = Invoice::find($checkTempData['data']->validated->target);
            $invoice->update(['status' => 1]);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Is Wrong')."..."]]);
        }

       return redirect()->route('invoice.transaction.success', $invoice->token)->with(['success' => [__('Transaction Successful')]]);
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


    // Razor Pay
    public function razorPaymentLink($trx_id){
        $identifier = $trx_id;
        $output = TemporaryData::where('identifier', $identifier)->first();
        if(!$output){
            return redirect()->route('index')->with(['error' => [__("Transaction failed, Please Try Again!")]]);
        }
        $data =  $output->data->response;
        $orderId =  $output->data->response->order_id;
        $page_title = __('RazorPay Payment');

        return view('frontend.payment.automatic.invoice-razor-link', compact('page_title','output','data','orderId'));
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
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEINVOICE)->responseReceive('razorpay');
                $invoice = Invoice::find($checkTempData['data']->validated->target);
                $invoice->update(['status' => 1]);
            }catch(Exception $e) {
                return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
            }

           return redirect()->route('invoice.transaction.success', $invoice->token)->with(['success' => [__('Transaction Successful')]]);

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
                PaymentGatewayHelper::init($checkTempData)->type(PaymentGatewayConst::TYPEINVOICE)->responseReceive('qrpay');
                $invoice = Invoice::find($checkTempData['data']->validated->target);
                $invoice->update(['status' => 1]);
            } catch (Exception $e) {
                return back()->with(['error' => [$e->getMessage()]]);
            }


            return redirect()->route('invoice.transaction.success', $invoice->token)->with(['success' => [__('Transaction Successful')]]);
        } else {
            return redirect()->route('index')->with(['error' => [__('Transaction Failed')]]);
        }
    }

    // QrPay Cancel
    public function qrpayCancel(Request $request, $trx_id)
    {
        TemporaryData::where("identifier", $trx_id)->delete();
        return redirect()->route("index")->with(['error' => [__('Payment Canceled')]]);
    }


    //coingate response start
    public function coinGateSuccess(Request $request, $gateway){
        try{
            $token = $request->token;
            $checkTempData = TemporaryData::where("type",PaymentGatewayConst::COIN_GATE)->where("identifier",$token)->first();
            $invoice = Invoice::find($checkTempData['data']->validated->target);

            if(!$checkTempData) return redirect()->route('index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);

            if(Transaction::where('callback_ref', $token)->exists()) {
                if(!$checkTempData)return redirect()->route('invoice.transaction.success', $invoice->token)->with(['success' => [__('Transaction Successful')]]);
            }else {
                if(!$checkTempData) return redirect()->route('index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
            }

            $update_temp_data = json_decode(json_encode($checkTempData->data),true);
            $update_temp_data['callback_data']  = $request->all();

            $checkTempData->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $checkTempData->toArray();
            PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEINVOICE)->responseReceive('coingate');
        }catch(Exception $e) {
            return redirect()->route("index")->with(['error' => [__('Something went wrong! Please try again.')]]);
        }

       return redirect()->route('invoice.transaction.success', $invoice->token)->with(['success' => [__('Transaction Successful')]]);
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
            PaymentGatewayHelper::init([])->type(PaymentGatewayConst::TYPEINVOICE)->handleCallback($callback_token,$callback_data,$gateway);
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
            $invoice = Invoice::find($temp_data['data']->validated->target);

            if(Transaction::where('callback_ref', $token)->exists()) {
                if(!$temp_data) return redirect()->route('invoice.transaction.success', $invoice->token)->with(['success' => [__('Transaction Successful')]]);
            }else {
                if(!$temp_data) return redirect()->route('index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
            }

            $update_temp_data = json_decode(json_encode($temp_data->data),true);
            $update_temp_data['callback_data']  = $request->all();
            $temp_data->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $temp_data->toArray();
            $instance = PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEINVOICE)->responseReceive('perfect-money');
            if($instance instanceof RedirectResponse) return $instance;
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }

        return redirect()->route('invoice.transaction.success', $invoice->token)->with(['success' => [__('Transaction Successful')]]);
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
            PaymentGatewayHelper::init([])->type(PaymentGatewayConst::TYPEINVOICE)->handleCallback($callback_token,$callback_data,$gateway);
        }catch(Exception $e) {
            // handle Error
            logger($e);
        }
    }


    public function successGlobal(Request $request, $gateway){
        try{
            $token = PaymentGatewayHelper::getToken($request->all(),$gateway);
            $temp_data = TemporaryData::where("identifier",$token)->first();
            $invoice = Invoice::find($temp_data['data']->validated->target);

            if(Transaction::where('callback_ref', $token)->exists()) {
                if(!$temp_data) return redirect()->route('invoice.transaction.success', $invoice->token)->with(['success' => [__('Transaction Successful')]]);
            }else {
                if(!$temp_data) return redirect()->route('index')->with(['error' => [__('Transaction failed. Record didn\'t saved properly. Please try again')]]);
            }

            $update_temp_data = json_decode(json_encode($temp_data->data),true);
            $update_temp_data['callback_data']  = $request->all();
            $temp_data->update([
                'data'  => $update_temp_data,
            ]);
            $temp_data = $temp_data->toArray();
            $instance = PaymentGatewayHelper::init($temp_data)->type(PaymentGatewayConst::TYPEINVOICE)->responseReceive($temp_data['type']);
            if($instance instanceof RedirectResponse) return $instance;
        }catch(Exception $e) {
            return back()->with(['error' => [$e->getMessage()]]);
        }
        return redirect()->route('invoice.transaction.success', $invoice->token)->with(['success' => [__('Transaction Successful')]]);
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
            return redirect()->route('index')->with(['error' => [__('Transaction Failed')]]);
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

}
