<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User\Invoice;
use Illuminate\Http\Request;
use App\Models\Admin\Currency;
use Illuminate\Support\Carbon;
use App\Models\User\InvoiceItem;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;
use App\Notifications\User\InvoiceNotification;
use App\Http\Helpers\Api\Helpers as ApiResponse;

class InvoiceController extends Controller
{
    /**
     * Invoice link page show
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function index(){
        $invoices = Invoice::auth()->with('invoiceItems')->where('user_id', Auth::id())->orderBy('id', 'desc')->get();

        $data = [
            'invoices'      => $invoices,
            'status' => [
                'Paid' => 1,
                'Unpaid' => 2,
                'Draft' => 3,
            ],
            'currency_data' => Currency::active()->get(),
        ];

        return ApiResponse::success(['success' => [__('Data fetched successfully')]], $data);
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
        $invoice_no = 'INV-'.generate_unique_string('invoices', 'invoice_no', 12, 'upper');

        $validator = Validator::make($request->all(), [
            'currency'        => 'required|string',
            'currency_symbol' => 'required|string',
            'country'         => 'required|string',
            'currency_name'   => 'required|string',
            'title'           => 'required|string',
            'name'            => 'required|string|max:120',
            'email'           => 'required|email',
            'phone'           => 'required',
            'total_qty'       => 'required|integer',
            'total_price'     => 'required|numeric',

            'item_title'    => 'required',
            'item_qty'      => 'required',
            'item_price'    => 'required',
        ]);


        if($validator->fails()){
            $error = ['error' => $validator->errors()->all()];
            return ApiResponse::onlyValidation($error);
        }


        $validated = $validator->validated();

        $validated['invoice_no'] = $invoice_no;
        $validated['token'] = $token;
        $validated['status'] = 3;
        $validated['user_id'] = Auth::id();
        $validated['qty'] = $validated['total_qty'];
        $validated['amount'] = $validated['total_price'];

        DB::beginTransaction();
        try {
            $invoice = Invoice::create($validated);

            $item_titles = explode(',',$validated['item_title']);
            $item_qtys   = explode(',', $validated['item_qty']);
            $item_prices  = explode(',', $validated['item_price']);

            $invoice_item = [];
            foreach ($item_titles as $key => $value) {
                $invoice_item[] = [
                    'invoice_item_id' => $invoice->id,
                    'title'           => $value,
                    'qty'             => $item_qtys[$key],
                    'price'           => $item_prices[$key],
                    'created_at'      => Carbon::now(),
                    'updated_at'      => Carbon::now(),
                ];
            }
            InvoiceItem::insert($invoice_item);
            $invoice = Invoice::with('invoiceItems','user')->where('id', $invoice->id)->first();
            $basic_settings = BasicSettings::first();

            if($basic_settings->email_notification){
                $invoice = Invoice::with('user','invoiceItems')->find($invoice->id);
                Notification::route('mail', [$invoice->email])->notify(new InvoiceNotification($invoice));
            }


            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return ApiResponse::onlyError(['error' => [__('Something went wrong! Please try again.')]]);
        }


        $data = [
            'invoice' => $invoice,
        ];

        return ApiResponse::success(['success' => [__('Invoice Created Successful.')]], $data);
    }


     /**
     * Invoice link store
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function update(Request $request){
        $invoice = Invoice::find($request->target);

        if(empty($invoice)){
            return ApiResponse::onlyError(['error' => [__('Invalid Request!')]]);
        }

        $validator = Validator::make($request->all(), [
            'currency'        => 'required|string',
            'currency_symbol' => 'required|string',
            'country'         => 'required|string',
            'currency_name'   => 'required|string',
            'title'           => 'required|string',
            'name'            => 'required|string|max:120',
            'email'           => 'required|email',
            'phone'           => 'required',
            'total_qty'       => 'required|integer',
            'total_price'     => 'required|numeric',

            'item_title'    => 'required',
            'item_qty'      => 'required',
            'item_price'    => 'required',
        ]);

        if($validator->fails()){
            $error = ['error' => $validator->errors()->all()];
            return ApiResponse::onlyValidation($error);
        }

        $validated = $validator->validated();

        $validated['status'] = 3;
        $validated['user_id'] = Auth::id();
        $validated['qty'] = $validated['total_qty'];
        $validated['amount'] = $validated['total_price'];

        DB::beginTransaction();
        try {

            $invoice->update($validated);

            InvoiceItem::where('invoice_item_id', $request->target)->delete();

            $item_titles = explode(',',$validated['item_title']);
            $item_qtys   = explode(',', $validated['item_qty']);
            $item_prices  = explode(',', $validated['item_price']);

            $invoice_item = [];
            foreach ($item_titles as $key => $value) {
                $invoice_item[] = [
                    'invoice_item_id' => $invoice->id,
                    'title'           => $value,
                    'qty'             => $item_qtys[$key],
                    'price'           => $item_prices[$key],
                    'created_at'      => Carbon::now(),
                    'updated_at'      => Carbon::now(),
                ];
            }

            InvoiceItem::insert($invoice_item);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            return ApiResponse::onlyError(['error' => [__('Something went wrong! Please try again.')]]);
        }


        $invoice = Invoice::with('invoiceItems')->where('id', $invoice->id)->first();

        $data = [
            'invoice' => $invoice,
        ];

        return ApiResponse::success(['success' => [__('Invoice Updated Successful.')]], $data);
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
            $error = ['error' => $validator->errors()->all()];
            return ApiResponse::onlyValidation($error);
        }

        $validated = $validator->validated();

        $invoice = Invoice::find($validated['target']);

        if(empty($invoice)){
            return ApiResponse::onlyError(['error' => [__('Invalid Request!')]]);
        }

        try {
            $invoice->update(['status' => $validated['status']]);

        } catch (\Exception $th) {
            return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
        }

        if($validated['status'] == 3){
            return ApiResponse::onlySuccess(['success' => [__('Invoice Save As Draft.')]]);
        }else{
            return ApiResponse::onlySuccess(['success' => [__('Invoice Published Successful.')]]);
        }

    }

     /**
     * Payment link store
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function edit(Request $request){

        $invoice = Invoice::with('invoiceItems')->find($request->target);

        if(empty($invoice)){
            return ApiResponse::onlyError(['error' => [__('Invalid Request!')]]);
        }
        $data = [
            'invoice' => (object) $invoice,
        ];

        return ApiResponse::success(['success' => [__('Data Fetch Successful.')]], $data);
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

        $invoice = Invoice::find($validated['target']);
        if(!$invoice){
            return ApiResponse::onlyError(['error' => [__('Invalid Request')]]);
        }

        if($invoice->status == 1){
            return ApiResponse::onlyError(['error' => [__('You Can Not Delete Paid Invoice')]]);
        }

        try {
            $invoice->delete();
        } catch (\Exception $e) {
            return ApiResponse::onlyError(['error' => [__('Something went wrong! Please try again.')]]);
        }

        return ApiResponse::onlySuccess(['success' => [__('Invoice Deleted Successful')]]);
    }


    public function downloadInvoice(Request $request)
    {
        $target = $request->target;

        $data = [
            'download_url' => route('invoice.download', $target),
        ];

        return ApiResponse::success(['success' => [__('Data Fetch Successful')]], $data);
    }

}
