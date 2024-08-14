<?php

namespace App\Http\Controllers\Api\V1;

use Exception;
use App\Models\Product;
use App\Models\ProductLink;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Models\Admin\Currency;
use App\Http\Controllers\Controller;
use App\Models\Admin\PaymentGateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Helpers\Api\Helpers as ApiResponse;

class ProductLinkController extends Controller
{
    /**
     * Product Link List
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function index(Request $request){

        $product = Product::find($request->product_id);
        if(empty($product)) return ApiResponse::onlyError(['error' => [__('Invalid Request!')]]);
        if($product->status == 2) return ApiResponse::onlyError(['error' => ['Currently, Your Product Is Inactive!']]);

        $product_links = ProductLink::auth()->with('product')->where('product_id',$request->product_id)->orderBy('id', 'desc')->get()->map(function($data){
            return [
                'product_links' => [
                        'id'              => $data->id,
                        'product_id'      => $data->product->id,
                        'currency'        => $data->currency,
                        'currency_name'   => $data->currency_name,
                        'currency_symbol' => $data->currency_symbol,
                        'country'         => $data->country,
                        'price'           => get_amount($data->price),
                        'qty'             => $data->qty,
                        'token'           => $data->token,
                        'status'          => $data->status,
                        'string_status'   => $data->stringStatus->value,
                        'created_at'      => $data->created_at,

                ],
            ];
        });

        $data = [
            'base_url'      => url('/'),
            'default_image' => get_files_public_path('default'),
            'image_path'    => get_files_public_path('products'),
            'currency_data' => Currency::active()->get(),
            'product_links'      => $product_links,
        ];


        return ApiResponse::success(['success' => [__('Data Fetch Successful')]], $data);
    }



    public function store(Request $request){
        $token = generate_unique_string('product_links', 'token', 60);

        $validator = Validator::make($request->all(), [
            'currency'   => 'required|exists:currencies,id',
            'product_id' => 'required',
            'price'      => 'required|numeric|min:1',
            'quantity'   => 'required|numeric|min:1',
        ]);

        if($validator->fails()){
            $error = ['error' => $validator->errors()->all()];
            return ApiResponse::onlyValidation($error);
        }

        $validated = $validator->validated();
        $currency = Currency::find($validated['currency']);
        if(empty($currency)){
            return ApiResponse::onlyError(['error' => [__('Currency Not Found!')]]);
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
            return ApiResponse::onlyError(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        $data = [
            'product_link' => $product_link,
        ];

        return ApiResponse::success(['success' => [__('Product Link Created Successful')]], $data);

    }



    /**
     * Product link edit page show
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function edit(Request $request){
        $product_link = ProductLink::find($request->target);

        if(empty($product_link)){
            return ApiResponse::onlyError(['error' => [__('Invalid Request!')]]);
        }
        $product_link = [
            'id'              => $product_link->id,
            'user_id'         => $product_link->user_id,
            'product_id'      => $product_link->product_id,
            'currency'        => $product_link->currency,
            'currency_name'   => $product_link->currency_name,
            'currency_symbol' => $product_link->currency_symbol,
            'country'         => $product_link->country,
            'price'           => $product_link->price,
            'quantity'        => $product_link->qty,
            'status'          => $product_link->status,

        ];


        $data = [
            'base_url'      => url('/'),
            'currency_data' => Currency::active()->get(),
            'product' => (object) $product_link,
        ];

        return ApiResponse::success(['success' => [__('Data Fetch Successful.')]], $data);
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

        if($validator->fails()){
            $error = ['error' => $validator->errors()->all()];
            return ApiResponse::onlyValidation($error);
        }

        $validated = $validator->validated();
        $currency = Currency::find($validated['currency']);

        if(empty($currency)){
            return ApiResponse::onlyError(['error' => [__('Currency Not Found!')]]);
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
            return ApiResponse::onlyError(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        $data = [
            'product_link' => $product_link,
        ];

        return ApiResponse::success(['success' => [__('Product Link Updated Successful')]],$data);

    }



    /**
     * Update Currency Status
     */
    public function status(Request $request) {
        $validator = Validator::make($request->all(),[
            'status'                    => 'required',
            'data_target'               => 'required|string',
        ]);
        if($validator->fails()){
            $error = ['error' => $validator->errors()->all()];
            return ApiResponse::onlyValidation($error);
        }
        $validated = $validator->safe()->all();

        $product_id = $validated['data_target'];
        $product = ProductLink::find($product_id);
        if(!$product){
            return ApiResponse::onlyError(['error' => [__('Product Link record not found in our system.')]]);
        }

        try{
            $product->update([
                'status' => $validated['status'],
            ]);
        }catch(Exception $e) {
            return ApiResponse::onlyError(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return ApiResponse::onlySuccess(['success' => [__('Product Link status updated successfully!')]]);

    }


    public function delete(Request $request) {
        $validator = Validator::make($request->all(),[
            'target'        => 'required|string|exists:product_links,id',
        ]);
        $validated = $validator->validate();
        if($validator->fails()){
            return back()->withErrors($validator)->withInput();
        }

        $product_link = ProductLink::find($validated['target']);
        if(!$product_link){
            return ApiResponse::onlyError(['error' => [__('Invalid Request')]]);
        }

        try{
            $product_link->delete();
        }catch(Exception $e) {
            return ApiResponse::onlyError(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }
        return ApiResponse::onlySuccess(['success' => [__('Product Link deleted successfully!')]]);
    }

}
