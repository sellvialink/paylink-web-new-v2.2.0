<?php

namespace App\Http\Controllers\User;

use Exception;
use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Models\Admin\Currency;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Product page show
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function index(){
        $page_title = __('Products');
        $products = Product::auth()->orderBy('id', 'desc')->paginate(12);
        return view('user.sections.product.index', compact('page_title','products'));
    }


    /**
     * Product create page show
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function create(){
        $page_title = __('Product Create');
        $currency_data = Currency::active()->get();
        return view('user.sections.product.create', compact('page_title','currency_data'));
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

        if($validator->stopOnFirstFailure()->fails()){
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $slug = Str::slug($validated['product_name']);

        $i = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = Str::slug($validated['product_name']) . '-' . $i++;
        }

        $currency = Currency::find($validated['currency']);

        if(empty($currency)){
            return back()->with(['error' => [__('Currency Not Found!')]]);
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
                    $image = get_files_from_fileholder($request,'image');
                    $upload_image = upload_files_from_path_dynamic($image,'products');
                    $product->update([
                        'image'  => $upload_image,
                    ]);
                }catch(Exception $e) {
                    return back()->withErrors($validator)->withInput()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
                }
            }

        } catch (\Exception $th) {
            return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
        }

        return redirect()->route('user.product.index')->with(['success' => [__('Product Created Successful')]]);
    }

    /**
     * Payment Update
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function update(Request $request){

        $validator = Validator::make($request->all(), [
            'currency'     => 'required|exists:currencies,id',
            'product_name' => 'required|string|max:180',
            'desc'         => 'nullable|string',
            'price'        => 'required|numeric|min:0.1',
            'image'        => 'nullable|image|mimes:png,jpg,jpeg,svg,webp',
            'product_id'   => 'required|exists:products,id',
        ]);

        if($validator->stopOnFirstFailure()->fails()){
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        $currency = Currency::find($validated['currency']);

        if(empty($currency)){
            return back()->with(['error' => [__('Currency Not Found!')]]);
        }

        $product = Product::find($validated['product_id']);

        $slug = Str::slug($validated['product_name']);

        $i = 1;
        while (Product::where('slug', $slug)->whereNot('id', $product->id)->exists()) {
            $slug = Str::slug($validated['product_name']) . '-' . $i++;
        }

        $validated['currency']        = $currency->code;
        $validated['currency_symbol'] = $currency->symbol;
        $validated['currency_name']   = $currency->name;
        $validated['country']         = $currency->country;

        $validated = Arr::except($validated, ['image']);
        $validated['user_id'] = Auth::id();

        try {

            $product->update($validated);

            if($request->hasFile('image')) {
                try{
                    $image = get_files_from_fileholder($request,'image');
                    $upload_image = upload_files_from_path_dynamic($image,'products');
                    delete_file(get_files_path('products').'/'.$product->image);
                    $product->update([
                        'image'  => $upload_image,
                    ]);
                }catch(Exception $e) {
                    return back()->withErrors($validator)->withInput()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
                }
            }

        } catch (\Exception $th) {
            return back()->with(['error' => [__('Something went wrong! Please try again.')]]);
        }

        return redirect()->route('user.product.index')->with(['success' => [__('Product Updated Successful')]]);
    }


    /**
     * Product create page show
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function edit($id){
        $page_title = __('Product Edit');
        $currency_data = Currency::active()->get();
        $product = Product::findOrFail($id);
        return view('user.sections.product.edit', compact('page_title','currency_data','product'));
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

        $product = Product::find($product_id);
        if(!$product) {
            $error = ['error' => [__('Product record not found in our system.')]];
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

        $success = ['success' => [__('Product status updated successfully!')]];
        return Response::success($success,null,200);
    }

    public function delete(Request $request) {
        $validator = Validator::make($request->all(),[
            'target'        => 'required|string|exists:products,id',
        ]);
        $validated = $validator->validate();
        $product = Product::find($validated['target']);

        try{
            $product->delete();
            delete_file(get_files_path('products').'/'.$product->image);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => [__('Product deleted successfully!')]]);
    }

}
