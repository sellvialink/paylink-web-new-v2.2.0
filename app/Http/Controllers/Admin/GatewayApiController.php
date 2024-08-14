<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Models\Admin\GatewayAPi;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GatewayApiController extends Controller
{
    public function index()
    {
        $page_title = __('Gateway Api');
        $api = GatewayAPi::first();
        return view('admin.sections.gateway-api.index',compact(
            'page_title',
            'api',
        ));
    }
    public function update(Request $request){

        $validator = Validator::make($request->all(), [
            'secret_key'  => 'required|string',
            'public_key' => 'required|string',
        ]);

        if($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();
        $validated['admin_id'] = Auth::id();

        try {
            GatewayAPi::updateOrCreate(['id' => 1],$validated);
        } catch (\Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong, Please Try Again!')]]);
        }

        return back()->with(['success' => [__('Stripe Api Key Updated Successful')]]);
    }
}
