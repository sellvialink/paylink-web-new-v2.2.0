<?php

namespace App\Http\Controllers\Api\V1;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Models\Admin\Currency;
use App\Models\User\PaymentLink;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\Validator;
use App\Http\Helpers\Api\Helpers as ApiResponse;

class PaymentLinkController extends Controller
{
    /**
     * Payment link List
     *
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function index(){
        $payment_links = PaymentLink::auth()->orderBy('id', 'desc')->get()->map(function($data){
            return [
                'id'            => $data->id,
                'currency'      => $data->currency,
                'currency_name' => $data->currency_name,
                'currency_symbol' => $data->currency_symbol,
                'country'       => $data->country,
                'type'          => $data->type,
                'token'         => $data->token,
                'title'         => $data->title,
                'image'         => $data->image,
                'details'       => $data->details,
                'limit'         => $data->limit,
                'min_amount'    => $data->min_amount ? get_amount($data->min_amount) : '',
                'max_amount'    => $data->max_amount ? get_amount($data->max_amount): '',
                'price'         => $data->price ? get_amount($data->price) : '',
                'qty'           => $data->qty,
                'status'        => $data->status,
                'string_status' => $data->stringStatus->value,
                'created_at'    => $data->created_at,
            ];
        });

        $data = [
            'base_url'      => url('/'),
            'default_image' => get_files_public_path('default'),
            'image_path'    => get_files_public_path('payment-link-image'),
            'currency_data' => Currency::active()->get(),
            'payment_links' => $payment_links,
        ];


        return ApiResponse::success(['success' => [__('Data Fetch Successful')]], $data);
    }


    /**
     * Payment link store
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function store(Request $request){

        $token = generate_unique_string('payment_links', 'token', 60);

        if($request->type == PaymentGatewayConst::LINK_TYPE_PAY){

            $validator = Validator::make($request->all(), [
                'currency'        => 'required|string',
                'currency_symbol' => 'required|string',
                'country'         => 'required|string',
                'currency_name'   => 'required|string',
                'title'           => 'required|string|max:180',
                'type'            => 'required|string',
                'details'         => 'nullable|string',
                'limit'           => 'nullable',
                'min_amount'      => 'nullable|numeric|min:0.1',
                'max_amount'      => 'nullable|numeric|gt:min_amount',
                'image'           => 'nullable|image|mimes:png,jpg,jpeg,svg,webp',
            ]);

            if($validator->fails()){
                $error = ['error' => $validator->errors()->all()];
                return ApiResponse::onlyValidation($error);
            }

            $validated = $validator->validated();
            $validated = Arr::except($validated, ['image']);
            $validated['limit'] = $request->limit ? 1 : 2;
            $validated['token'] = $token;
            $validated['status'] = 1;
            $validated['user_id'] = Auth::id();

            try {
                $payment_link = PaymentLink::create($validated);

                if($request->hasFile('image')) {
                    try{
                        $image = upload_file($request->image,'payment-link-image');
                        $upload_image = upload_files_from_path_dynamic([$image['dev_path']],'payment-link-image');
                        delete_file($image['dev_path']);
                        $payment_link->update([
                            'image'  => $upload_image,
                        ]);
                    }catch(Exception $e) {
                        return ApiResponse::onlyError(['error' => [__('Something went wrong! Please try again.')]]);
                    }
                }

            } catch (\Exception $th) {
                return ApiResponse::onlyError(['error' => [__('Something went wrong! Please try again.')]]);
            }

        }else{
            $validator = Validator::make($request->all(), [
                'sub_currency'    => 'required',
                'currency_symbol' => 'required',
                'currency_name'   => 'required',
                'country'         => 'required',
                'sub_title'       => 'required|max:180',
                'type'            => 'required',
                'price'           => 'nullable:numeric',
                'qty'             => 'nullable:integer',
            ]);


            if($validator->fails()){
                $error = ['error' => $validator->errors()->all()];
                return ApiResponse::onlyValidation($error);
            }

            $validated = $validator->validated();
            $validated['currency'] = $validated['sub_currency'];
            $validated['title'] = $validated['sub_title'];
            $validated['token'] = $token;
            $validated['status'] = 1;
            $validated['user_id'] = Auth::id();

            $validated = Arr::except($validated, ['sub_currency','sub_title']);

            try {
                $payment_link = PaymentLink::create($validated);
            } catch (\Exception $th) {
                return ApiResponse::onlyError(['error' => [__('Something went wrong! Please try again.')]]);
            }
        }

        $data = [
            'payment_link' => $payment_link,
        ];

        return ApiResponse::success(['success' => [__('Payment Link Created Successful.')]], $data);
    }


    /**
     * Payment link store
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function edit(Request $request){

        $paymentLink = PaymentLink::find($request->target);

        if(empty($paymentLink)){
            return ApiResponse::onlyError(['error' => [__('Invalid Request!')]]);
        }

        $payment_link_data = [
                'id'              => $paymentLink->id,
                'currency'        => $paymentLink->currency,
                'currency_name'   => $paymentLink->currency_name,
                'currency_symbol' => $paymentLink->currency_symbol,
                'country'         => $paymentLink->country,
                'type'            => $paymentLink->type,
                'token'           => $paymentLink->token,
                'title'           => $paymentLink->title,
                'image'           => $paymentLink->image,
                'details'         => $paymentLink->details,
                'limit'           => $paymentLink->limit,
                'min_amount'      => $paymentLink->min_amount,
                'max_amount'      => $paymentLink->max_amount,
                'price'           => $paymentLink->price,
                'qty'             => $paymentLink->qty,
                'status'          => $paymentLink->status,
                'string_status'   => $paymentLink->stringStatus->value,
                'created_at'      => $paymentLink->created_at,
        ];

        $data = [
            'base_url'      => url('/'),
            'default_image' => get_files_public_path('default'),
            'image_path'    => get_files_public_path('payment-link-image'),
            'currency_data' => Currency::active()->get(),
            'payment_link' => (object) $payment_link_data,
        ];

        return ApiResponse::success(['success' => [__('Data Fetch Successful.')]], $data);
    }

    /**
     * Payment link store
     *
     * @param Illuminate\Http\Request $request
     * @method POST
     * @return Illuminate\Http\Request
     */
    public function update(Request $request){

        $paymentLink = PaymentLink::find($request->target);

        if(empty($paymentLink)){
            return ApiResponse::onlyError(['error' => [__('Invalid Request!')]]);
        }

        if($request->type == PaymentGatewayConst::LINK_TYPE_PAY){
            $validator = Validator::make($request->all(), [
                'currency'        => 'required',
                'currency_symbol' => 'required',
                'currency_name'   => 'required',
                'title'           => 'required|max:180',
                'type'            => 'required',
                'details'         => 'nullable',
                'limit'           => 'nullable',
                'min_amount'      => 'nullable|min:0.1',
                'max_amount'      => 'nullable|gt:min_amount',
                'image'           => 'nullable|image|mimes:png,jpg,jpeg,svg,webp',
            ]);

            if($validator->fails()){
                $error = ['error' => $validator->errors()->all()];
                return ApiResponse::onlyValidation($error);
            }

            $validated = $validator->validated();

            if($paymentLink->type == PaymentGatewayConst::LINK_TYPE_SUB){
                $validated['price'] = NULL;
                $validated['qty'] = NULL;
            }


            $validated = Arr::except($validated, ['image']);
            $validated['limit'] = $request->limit ? 1 : 2;
            $validated['user_id'] = Auth::id();

            try {

                if($request->hasFile('image')) {
                    try{
                        $image = upload_file($request->image,'payment-link-image', $paymentLink->image);
                        $upload_image = upload_files_from_path_dynamic([$image['dev_path']],'payment-link-image', $paymentLink->image);
                        delete_file($image['dev_path']);
                        delete_file(get_files_path('payment-link-image').'/'.$paymentLink->image);
                        $validated['image'] = $upload_image;
                    }catch(Exception $e) {
                        return ApiResponse::onlyError(['error' => [__('Something went wrong! Please try again.')]]);
                    }
                }

                $paymentLink->update($validated);

            } catch (\Exception $th) {
                 return ApiResponse::onlyError(['error' => [__('Something went wrong! Please try again.')]]);
            }

        }else{
            $validator = Validator::make($request->all(), [
                'sub_currency'    => 'required',
                'currency_symbol' => 'required',
                'currency_name'   => 'required',
                'sub_title'       => 'required|max:180',
                'type'            => 'required',
                'price'           => 'nullable',
                'qty'             => 'nullable',
            ]);

            $validated = $validator->validated();
            $validated['currency'] = $validated['sub_currency'];
            $validated['title'] = $validated['sub_title'];
            $validated['user_id'] = Auth::id();

            if($paymentLink->type == PaymentGatewayConst::LINK_TYPE_PAY){

                $validated['image']      = NULL;
                $validated['details']    = NULL;
                $validated['limit']      = 2;
                $validated['min_amount'] = NULL;
                $validated['max_amount'] = NULL;

                $image_link = get_files_path('payment-link-image') . '/' . $paymentLink->image;
                delete_file($image_link);
            }

            $validated = Arr::except($validated, ['sub_currency','sub_title']);

            try {
                $paymentLink->update($validated);
            } catch (\Exception $th) {
                 return ApiResponse::onlyError(['error' => [__('Something went wrong! Please try again.')]]);
            }
        }


        $data = [
            'payment_link' => $paymentLink,
        ];

        return ApiResponse::success(['success' => [__('Payment Link Updated Successful.')]], $data);
    }


    /**
     * Payment link store
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
        $paymentLink = PaymentLink::find($validated['target']);

        try {
            $status = $paymentLink->status == 1 ? 2 : 1;
            $paymentLink->update(['status' => $status]);

        } catch (\Exception $th) {
            return ApiResponse::onlyError(['error' => [__('Something went wrong! Please try again.')]]);
        }


        return ApiResponse::onlySuccess(['success' => [__('Status Change Successful.')]]);
    }
}
