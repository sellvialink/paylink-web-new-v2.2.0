<?php

namespace App\Http\Controllers\User;

use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Admin\PaymentGateway;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    protected $basic_settings;

    public function __construct()
    {
        $this->basic_settings = BasicSettingsProvider::get();
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = __('User Profile');
        $user = auth()->user();
        $gatewaies = PaymentGateway::moneyOut()->manual()->get();
        return view('user.sections.profile.index',compact("page_title","user", "gatewaies"));
    }
    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request)
    {

        $validated = Validator::make($request->all(),[
            'first_name'   => 'required|string|max:60',
            'last_name'    => 'required|string|max:60',
            'company_name' => 'required|string|max:180',
            'city'         => 'nullable|string',
            'state'        => 'nullable|string',
            'zip_code'     => 'nullable|string',
            'address'      => 'nullable|string',
            'mobile'       => 'nullable|string|unique:users,mobile,'.auth()->user()->id,
            'image'        => "nullable|image|mimes:jpg,png,jpeg",
        ])->validate();

        $validated['mobile']         = remove_speacial_char($validated['mobile']);
        $validated['firstname'] = $validated['first_name'];
        $validated['lastname'] = $validated['last_name'];

        $validated['address']       = [
            'country'      => auth()->user()->address->country,
            'city'         => $validated['city'] ?? null,
            'state'        => $validated['state'] ?? null,
            'address'      => $validated['address'] ?? null,
            'zip_code'     => $validated['zip_code'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
        ];

        if($request->hasFile("image")) {
            $image = upload_file($validated['image'],'user-profile',auth()->user()->image);
            $upload_image = upload_files_from_path_dynamic([$image['dev_path']],'user-profile');
            delete_file($image['dev_path']);
            $validated['image']     = $upload_image;
        }
        try{
            auth()->user()->update($validated);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => ['Profile successfully updated!']]);
    }


    public function passwordUpdate(Request $request) {

        $basic_settings = BasicSettingsProvider::get();
        $passowrd_rule = "required|string|min:6|confirmed";

        if($basic_settings->secure_password) {
            $passowrd_rule = ["required",Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised(),"confirmed"];
        }

        $request->validate([
            'current_password'      => "required|string",
            'password'              => $passowrd_rule,
        ]);

        if(!Hash::check($request->current_password,auth()->user()->password)) {
            throw ValidationException::withMessages([
                'current_password'      => 'Current password didn\'t match',
            ]);
        }

        try{
            auth()->user()->update([
                'password'  => Hash::make($request->password),
            ]);
        }catch(Exception $e) {
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return back()->with(['success' => ['Password successfully updated!']]);

    }


}
