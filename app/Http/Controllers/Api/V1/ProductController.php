<?php

namespace App\Http\Controllers\Api\V1;

use Exception;
use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Admin\Currency;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Http\Helpers\Api\Helpers as ApiResponse;

class ProductController extends Controller
{
    /**
     * Product List
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function index(){
        $products = Product::auth()->orderBy('id', 'desc')->get()->map(function($data){
            return [
                'id'              => $data->id,
                'currency'        => $data->currency,
                'currency_name'   => $data->currency_name,
                'currency_symbol' => $data->currency_symbol,
                'country'         => $data->country,
                'product_name'    => $data->product_name,
                'image'           => $data->image,
                'desc'            => $data->desc,
                'price'           => get_amount($data->price),
                'status'          => $data->status,
                'string_status'   => $data->stringStatus->value,
                'created_at'      => $data->created_at,
            ];
        });

        $data = [
            'base_url'      => url('/'),
            'default_image' => get_files_public_path('default'),
            'image_path'    => get_files_public_path('products'),
            'currency_data' => Currency::active()->get(),
            'products'      => $products,
        ];


        return ApiResponse::success(['success' => [__('Data Fetch Successful')]], $data);
    }

     /**
     * Product store
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function store(Request $request){

        $validator = Validator::make($request->all(), [
            'currency'     => 'required|exists:currencies,id',
            'product_name' => 'required|string|max:180',
            'desc'         => 'nullable|string|max:400',
            'price'        => 'required|numeric|min:0.1',
            'image'        => 'nullable|image|mimes:png,jpg,jpeg,svg,webp',
        ]);

        if($validator->fails()){
            $error = ['error' => $validator->errors()->all()];
            return ApiResponse::onlyValidation($error);
        }

        $validated = $validator->validated();

        $slug = Str::slug($validated['product_name']);

        $i = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = Str::slug($validated['product_name']) . '-' . $i++;
        }

        $currency = Currency::find($validated['currency']);

        if(empty($currency)){
            return ApiResponse::onlyError(['error' => [__('Currency Not Found!')]]);
        }

        $validated['currency']        = $currency->code;
        $validated['currency_symbol'] = $currency->symbol;
        $validated['currency_name']   = $currency->name;
        $validated['country']         = $currency->country;
        $validated['slug']            = $slug;

        $validated = Arr::except($validated, ['image']);
        $validated['status'] = 1;
        $validated['user_id'] = Auth::id();

        try {
            $product = Product::create($validated);

            if($request->hasFile('image')) {
                try{
                    $image = upload_file($request->image,'products');
                    $upload_image = upload_files_from_path_dynamic([$image['dev_path']],'products');
                    delete_file($image['dev_path']);
                    $product->update([
                        'image'  => $upload_image,
                    ]);
                }catch(Exception $e) {
                    return ApiResponse::onlyError(['error' => [__('Something Went Wrong! Please Try Again.')]]);
                }
            }

        } catch (\Exception $e) {
            return ApiResponse::onlyError(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        $data = [
            'product' => $product,
        ];

        return ApiResponse::success(['success' => [__('Product Created Successful')]], $data);
    }

    /**
     * Product store
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function edit(Request $request){

        $product = Product::find($request->target);

        if(empty($product)){
            return ApiResponse::onlyError(['error' => [__('Invalid Request!')]]);
        }

        $product = [
            'id'              => $product->id,
            'currency'        => $product->currency,
            'currency_name'   => $product->currency_name,
            'currency_symbol' => $product->currency_symbol,
            'country'         => $product->country,
            'product_name'    => $product->product_name,
            'image'           => $product->image,
            'desc'            => $product->desc,
            'price'           => get_amount($product->price),
            'status'          => $product->status,
            'string_status'   => $product->stringStatus->value,
            'created_at'      => $product->created_at,
        ];

        $data = [
            'base_url'      => url('/'),
            'default_image' => get_files_public_path('default'),
            'image_path'    => get_files_public_path('products'),
            'currency_data' => Currency::active()->get(),
            'product' => (object) $product,
        ];

        return ApiResponse::success(['success' => [__('Data Fetch Successful.')]], $data);
    }

    /**
     * Product store
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function update(Request $request){

        $validator = Validator::make($request->all(), [
            'currency'     => 'required|exists:currencies,id',
            'product_name' => 'required|string|max:180',
            'desc'         => 'nullable|string|max:400',
            'price'        => 'required|numeric|min:0.1',
            'image'        => 'nullable|image|mimes:png,jpg,jpeg,svg,webp',
            'target'       => 'required|exists:products,id',
        ]);

        if($validator->fails()){
            $error = ['error' => $validator->errors()->all()];
            return ApiResponse::onlyValidation($error);
        }

        $validated = $validator->validated();

        $product = Product::find($validated['target']);

        $slug = Str::slug($validated['product_name']);

        $i = 1;
        while (Product::where('slug', $slug)->whereNot('id', $product->id)->exists()) {
            $slug = Str::slug($validated['product_name']) . '-' . $i++;
        }

        $currency = Currency::find($validated['currency']);

        if(empty($currency)){
            return ApiResponse::onlyError(['error' => [__('Currency Not Found!')]]);
        }

        $validated['currency']        = $currency->code;
        $validated['currency_symbol'] = $currency->symbol;
        $validated['currency_name']   = $currency->name;
        $validated['country']         = $currency->country;
        $validated['slug']            = $slug;

        $validated = Arr::except($validated, ['image']);
        $validated['user_id'] = Auth::id();

        try {
            $product->update($validated);

            if($request->hasFile('image')) {
                try{
                    $image = upload_file($request->image,'products');
                    $upload_image = upload_files_from_path_dynamic([$image['dev_path']],'products');
                    delete_file($image['dev_path']);
                    delete_file(get_files_path('products').'/'.$product->image);
                    $product->update([
                        'image'  => $upload_image,
                    ]);
                }catch(Exception $e) {
                    return ApiResponse::onlyError(['error' => [__('Something Went Wrong! Please Try Again.')]]);
                }
            }

        } catch (\Exception $e) {
            return ApiResponse::onlyError(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        $data = [
            'product' => $product,
        ];

        return ApiResponse::success(['success' => [__('Product Updated Successful')]], $data);
    }

    /**
     * Product store
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function status(Request $request){

        $validator = Validator::make($request->all(), [
            'target'        => 'required',
        ]);

        if($validator->fails()){
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();
        $product = Product::find($validated['target']);

        if(!$product){
            return ApiResponse::onlyError(['error' => [__('Invalid Request')]]);
        }

        try {
            $status = $product->status == 1 ? 2 : 1;
            $product->update(['status' => $status]);
        } catch (\Exception $th) {
            return ApiResponse::onlyError(['error' => [__('Something went wrong! Please try again.')]]);
        }


        return ApiResponse::onlySuccess(['success' => [__('Status Change Successful.')]]);
    }

    /**
     * Product Delete
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

        $product = Product::find($validated['target']);
        if(!$product){
            return ApiResponse::onlyError(['error' => [__('Invalid Request')]]);
        }

        try {
            delete_file(get_files_path('products').'/'.$product->image);
            $product->delete();
        } catch (\Exception $e) {
            return ApiResponse::onlyError(['error' => [__('Something went wrong! Please try again.')]]);
        }

        return ApiResponse::onlySuccess(['success' => [__('Product deleted successfully!')]]);
    }
}
