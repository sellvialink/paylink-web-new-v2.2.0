<?php

namespace App\Http\Controllers\User;

use Exception;
use Illuminate\Http\Request;
use App\Constants\GlobalConst;
use App\Models\Admin\SetupKyc;
use Illuminate\Support\Carbon;
use App\Models\UserAuthorization;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Traits\ControlDynamicInputFields;
use Illuminate\Support\Facades\Validator;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Validation\ValidationException;
use App\Notifications\User\Auth\SendAuthorizationCode;

class AuthorizationController extends Controller
{
    use ControlDynamicInputFields;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function showMailFrom($token)
    {
        $user_authorize = UserAuthorization::where("token",$token)->first();
        $resend_time = 0;if(Carbon::now() <= $user_authorize->created_at->addMinutes(GlobalConst::USER_PASS_RESEND_TIME_MINUTE)) {
            $resend_time = Carbon::now()->diffInSeconds($user_authorize->created_at->addMinutes(GlobalConst::USER_PASS_RESEND_TIME_MINUTE));
        }

        $page_title = setPageTitle("Mail Authorization");

        $user_email = $user_authorize->user->email ?? '';

        return view('user.auth.authorize.verify-mail',compact("page_title","token","resend_time","user_email"));
    }

    /**
     * Verify authorizaation code.
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function mailVerify(Request $request,$token)
    {
        $request->merge(['token' => $token]);
        $request->validate([
            'token'     => "required|string|exists:user_authorizations,token",
            'code*'      => "required|integer",
        ]);

        $code = implode($request->code);


        $otp_exp_sec = BasicSettingsProvider::get()->otp_exp_seconds ?? GlobalConst::DEFAULT_TOKEN_EXP_SEC;
        $auth_column = UserAuthorization::where("token",$request->token)->where("code",$code)->first();

        if(!$auth_column){
            return redirect()->back()->with(['error' => ['Invalid otp code']]);
        }

        if($auth_column->created_at->addSeconds($otp_exp_sec) < now()) {
            $this->authLogout($request);
            return redirect()->route('user.login')->with(['error' => ['Session expired. Please try again']]);
        }
        try{
            $auth_column->user->update([
                'email_verified'    => true,
            ]);
            $auth_column->delete();
        }catch(Exception $e) {
            $this->authLogout($request);
            return redirect()->route('user.login')->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return redirect()->intended(route("user.dashboard"))->with(['success' => ['Email successfully verified']]);
    }

    public function authLogout(Request $request) {
        auth()->guard("web")->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function mailResendToken($token) {
        $user_authorize = UserAuthorization::where("token",$token)->first();
        $resend_code = generate_random_code();
        try{
            $user_authorize->update([
                'code'          => $resend_code,
                'created_at'    => now(),
            ]);
            $data = $user_authorize->toArray();
            $user_authorize->user->notify(new SendAuthorizationCode((object) $data));
        }catch(Exception $e) {
            throw ValidationException::withMessages([
                'code'      => "Something went wrong! Please try again.",
            ]);
        }
        return redirect()->route('user.authorize.mail',$token)->with(['success' => ['Mail OTP Resend Success!']]);
    }

    public function showKycFrom() {
        $basic_settings   = BasicSettings::first();
        if($basic_settings['kyc_verification'] == false) return back()->with(['success' => ['Do not need to identity verification!!']]);

        $user = auth()->user();
        $page_title = __('Proof Of Identity');
        $user_kyc = SetupKyc::userKyc()->first();
        if(!$user_kyc) return back()->with(['success' => ['Do not need to identity verification!!']]);
            $kyc_data = $user_kyc->fields;
            $kyc_fields = [];
        if($kyc_data) {
            $kyc_fields = array_reverse($kyc_data);
        }
        return view('user.sections.kyc.verify-kyc',compact("page_title","kyc_fields","user_kyc"));
    }

    public function kycSubmit(Request $request) {

        $user = auth()->user();
        if($user->kyc_verified == GlobalConst::VERIFIED) return back()->with(['success' => ['You are already KYC Verified User']]);

        $user_kyc_fields = SetupKyc::userKyc()->first()->fields ?? [];
        $validation_rules = $this->generateValidationRules($user_kyc_fields);

        $validated = Validator::make($request->all(),$validation_rules)->validate();
        $get_values = $this->placeValueWithFields($user_kyc_fields,$validated);

        $create = [
            'user_id'       => auth()->user()->id,
            'data'          => json_encode($get_values),
            'created_at'    => now(),
        ];

        DB::beginTransaction();
        try{
            DB::table('user_kyc_data')->updateOrInsert(["user_id" => $user->id],$create);
            $user->update([
                'kyc_verified'  => GlobalConst::PENDING,
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            $user->update([
                'kyc_verified'  => GlobalConst::DEFAULT,
            ]);
            $this->generatedFieldsFilesDelete($get_values);
            return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return redirect()->route('user.authorize.kyc')->with(['success' => ['KYC information successfully submited']]);
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function showSMSFrom($token)
    {
        $user_authorize = UserAuthorization::where("token",$token)->first();
        $resend_time = 0;
        if(Carbon::now() <= $user_authorize->created_at->addMinutes(GlobalConst::USER_PASS_RESEND_TIME_MINUTE)) {
            $resend_time = Carbon::now()->diffInSeconds($user_authorize->created_at->addMinutes(GlobalConst::USER_PASS_RESEND_TIME_MINUTE));
        }
        $page_title = setPageTitle("SMS Authorization");
        return view('user.auth.authorize.verify-sms',compact("page_title","token","resend_time"));
    }

    public function smsResendToken($token) {
        $user_authorize = UserAuthorization::where("token",$token)->first();
        $resend_code = generate_random_code();
        try{
            $user_authorize->update([
                'code'          => $resend_code,
                'created_at'    => now(),
            ]);

            $data = $user_authorize->toArray();
            $message = 'Your '.env('APP_NAME').' verification code is'.$data['code'];

            $basic_settings = BasicSettingsProvider::get();
            sendTwilioMessage($message, Auth::user()->full_mobile, $basic_settings);

        }catch(Exception $e) {
            throw ValidationException::withMessages([
                'code'      => "Something went wrong! Please try again.",
            ]);
        }
        return redirect()->route('user.authorize.sms',$token)->with(['success' => ['SMS OTP Resend successfully!']]);
    }

    /**
     * Verify authorizaation code.
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function smsVerify(Request $request,$token)
    {
        $request->merge(['token' => $token]);
        $request->validate([
            'token'     => "required|string|exists:user_authorizations,token",
            'code*'      => "required|integer",
        ]);

        $code = implode($request->code);

        $otp_exp_sec = BasicSettingsProvider::get()->otp_exp_seconds ?? GlobalConst::DEFAULT_TOKEN_EXP_SEC;
        $auth_column = UserAuthorization::where("token",$request->token)->where("code",$code)->first();
        if($auth_column->created_at->addSeconds($otp_exp_sec) < now()) {
            $this->authLogout($request);
            return redirect()->route('user.login')->with(['error' => ['Session expired. Please try again']]);
        }
        try{
            $auth_column->user->update([
                'sms_verified'    => true,
            ]);
            $auth_column->delete();
        }catch(Exception $e) {
            $this->authLogout($request);
            return redirect()->route('user.login')->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
        }

        return redirect()->intended(route("user.dashboard"))->with(['success' => ['Account successfully verified']]);
    }

    /**
     * Googel 2FA Form
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function showGoogle2FAForm() {
        $page_title =  "Authorize Google Two Factor";
        return view('user.auth.authorize.verify-g2fa',compact('page_title'));
    }
    public function google2FASubmit(Request $request) {
        $request->validate([
            'code*'    => "required|numeric",
        ]);
        $code = implode($request->code);;
        $user = auth()->user();
        if(!$user->two_factor_secret) {
            return back()->with(['warning' => ['Your secret key not stored properly. Please contact with system administrator']]);
        }

        if(google_2fa_verify($user->two_factor_secret,$code)) {
            $user->update([
                'two_factor_verified'   => true,
            ]);
            return redirect()->intended(route('user.dashboard'));
        }
        return back()->with(['warning' => ['Failed to login. Please try again']]);
    }
}
