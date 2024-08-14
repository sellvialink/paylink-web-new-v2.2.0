<?php

namespace App\Http\Controllers\Api\V1\Auth;

use Exception;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Models\UserAuthorization;
use App\Models\Admin\ExchangeRate;
use App\Traits\User\LoggedInUsers;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Traits\User\RegisteredUsers;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\User\UserResouce;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Providers\Admin\BasicSettingsProvider;
use App\Http\Helpers\Api\Helpers as ApiResponse;
use App\Notifications\User\Auth\SendAuthorizationCode;

class AuthController extends Controller
{
    use LoggedInUsers, RegisteredUsers;
    protected $basic_settings;

    public function __construct()
    {
        $this->basic_settings = BasicSettingsProvider::get();
    }

    /**
     * Mehtod for user login
     * @method POST
     * @param \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Request  Response
     */
    public function login(Request $request){
        $validator = Validator::make($request->all(),[
            'email'    => 'required|max:40',
            'password' => 'required|min:6',
        ]);

        if($validator->fails()){
            $error = ['error' => $validator->errors()->all()];
            return ApiResponse::onlyValidation($error);
        }

        $user = User::where('username', trim(strtolower($request->email)))->orWhere('email', $request->email)->first();

        if(!$user){
            $error = ['error' => ['The credentials does not match']];
            return ApiResponse::onlyValidation($error);
        }

        $user->two_factor_verified = false;
        $user->save();

        $token = $user->createToken('Laravel Password Grant Client')->accessToken;

        $user_data = [
            'token'         => $token,
            'image_path'    => get_files_public_path('user-profile'),
            'default_image' => get_files_public_path('default'),
            "base_ur"       => url('/'),
            'user'          => new UserResouce($user)
        ];

        if(Hash::check($request->password, $user->password)){
            if($user->status == 0){
                $error = ['error'=>[__('Account Has been Suspended')]];
                return ApiResponse::onlyValidation($error);
            }elseif($user->email_verified == 0){
                $user_authorize = UserAuthorization::where("user_id",$user->id)->first();
                $resend_code = generate_random_code();
                $user_authorize->update([
                    'code'          => $resend_code,
                    'created_at'    => now(),
                ]);
                $data = $user_authorize->toArray();
                $user->notify(new SendAuthorizationCode((object) $data));
                $message = ['success' => [__('Please check email and verify your account')]];
                return ApiResponse::success($message, $user_data);
            }else if($user->sms_verified == 0){
                $user_authorize = UserAuthorization::where("user_id",$user->id)->first();
                $resend_code = generate_random_code();
                $user_authorize->update([
                    'code'          => $resend_code,
                    'created_at'    => now(),
                ]);

                $basic_settings = BasicSettingsProvider::get();

                $message = 'Your '.env('APP_NAME').' verification code is'.$resend_code;

                if ($basic_settings->sms_config->name == 'twilio') {
                    sendTwilioMessage($message, $user->full_mobile, $basic_settings);
                }

                $message = ['success' => [__('Please check your mobile and verify your account')]];
                return ApiResponse::success($message, $user_data);
            }

            $this->refreshUserWallets($user);
            $this->createLoginLog($user);

            $message = ['success' => [__('Login Successful')]];
            return ApiResponse::success($message,$user_data);
        }else{
            $error = ['error'=>[__('The credentials does not match')]];
            return ApiResponse::onlyError($error);
        }
    }

    /**
     * Mehtod for user register
     * @method POST
     * @param \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Request  Response
     */

    public function register(Request $request){
        $basic_settings = $this->basic_settings;
        $passowrd_rule = "required|string|min:6";

        if($basic_settings->secure_password) {
            $passowrd_rule = ["required",Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()];
        }

        $agree_policy = $this->basic_settings->agree_policy == 1 ? 'required|in:on' : 'nullable';

        $validator = Validator::make($request->all(), [
            'first_name'   => 'required|string|max:50',
            'last_name'    => 'required|string|max:50',
            'company_name' => 'required|string|max:180',
            'country'      => 'required|string',
            'email'        => 'required|email|max:160|unique:users',
            'password'     => $passowrd_rule,
            'policy'       => $agree_policy,
        ]);

        if($validator->fails()){
            $error =  ['error'=>$validator->errors()->all()];
            return ApiResponse::onlyValidation($error);
        }

        $validated = $validator->validated();


        $exchange_rate = ExchangeRate::where('name', $validated['country'])->first();

        if(empty($exchange_rate)){
            return ApiResponse::onlyValidation(['error' => [__('Your Selected Country Dose Not Support To Create Account')]]);
        }

        $basic_settings             = $this->basic_settings;
        //User Create

        $validated = Arr::except($validated,['agree']);

        $validated['firstname']      = $validated['first_name'];
        $validated['lastname']       = $validated['last_name'];

        $validated['email_verified'] = ($basic_settings->email_verification == true) ? 0 : 1;
        $validated['kyc_verified']   = ($basic_settings->kyc_verification == true) ? 0 : 1;
        $validated['sms_verified']   = ($basic_settings->sms_verification == true) ? 0 : 1;
        $validated['status']         = 1;

        $validated['password']       = Hash::make($validated['password']);
        $validated['username']       = make_username($validated['first_name'],$validated['last_name']);

        $validated['address']       = [
            'country'  => $validated['country'] ?? null,
            'city'     => $validated['city'] ?? null,
            'state'    => $validated['state'] ?? null,
            'address'  => $validated['address'] ?? null,
            'zip_code' => $validated['zip_code'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
        ];

        $user = User::create($validated);

        $token = $user->createToken('Laravel Password Grant Client')->accessToken;

        $this->createUserWallets($user);

        if ($basic_settings->email_verification == true) {
            $data = [
                'user_id'       => $user->id,
                'code'          => generate_random_code(),
                'token'         => generate_unique_string("user_authorizations","token",200),
                'created_at'    => now(),
            ];

            DB::beginTransaction();
            try{
                UserAuthorization::where("user_id",$user->id)->delete();
                DB::table("user_authorizations")->insert($data);
                $user->notify(new SendAuthorizationCode((object) $data));
                DB::commit();
            }catch(Exception $e) {
                DB::rollBack();
                $error = ['error'=>[__('Something Went Wrong! Please Try Again.')]];
                return ApiResponse::error($error);
            }
        }

        if ($basic_settings->email_verification == 1) {
            $message =  ['success' => [__('Please check email and verify your account')]];
        } else {
            $message =  ['success' => [__('Registration successful')]];
        }

        $data = [
            'token' => $token,
            'image_path' => get_files_public_path('user-profile'),
            'default_image' => get_files_public_path('default'),
            "base_ur"       => url('/'),
            'user' => new UserResouce($user)
        ];

        return ApiResponse::success($message, $data);
    }
}
