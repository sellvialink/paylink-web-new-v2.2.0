<?php

namespace App\Http\Controllers\User\Auth;

use Exception;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Http\Request;
use App\Models\Admin\ExchangeRate;
use App\Http\Controllers\Controller;
use App\Traits\User\RegisteredUsers;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Validation\ValidationException;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers, RegisteredUsers;

    protected $basic_settings;

    public function __construct()
    {
        $this->basic_settings = BasicSettingsProvider::get();
    }

    /**
     * Show the application registration form.
     *
     * @return \Illuminate\View\View
     */
    public function showRegistrationForm() {

        if($agree_policy = $this->basic_settings->user_registration == 0){
            abort(404);
        }

        $client_ip = request()->ip() ?? false;
        $user_country = geoip()->getLocation($client_ip)['country'] ?? "";

        $exchange_rates = ExchangeRate::where('status', 1)->orderBy('name', 'asc')->get();

        $page_title = __('User Registration');
        return view('user.auth.register',compact(
            'page_title',
            'user_country',
            'exchange_rates',
        ));
    }

    /**
     * Handle a registration request for the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {

        $validator                   = $this->validator($request->all());

        if($validator->fails()){
            return back()->withErrors($validator)->withInput()->with('session_error', 'register');
        }

        $validated = $validator->validated();

        $exchange_rate = ExchangeRate::where('name', $validated['country'])->first();

        if(empty($exchange_rate)){
            return back()->with(['error' => ['Your Selected Country Dose Not Support To Create Account']]);
        }

        $basic_settings              = $this->basic_settings;

        $validated                   = Arr::except($validated,['agree']);

        $validated['email_verified'] = ($basic_settings->email_verification == true) ? false : true;
        $validated['sms_verified']   = ($basic_settings->sms_verification == true) ? false : true;
        $validated['kyc_verified']   = ($basic_settings->kyc_verification == true) ? false : true;

        $validated['password']       = Hash::make($validated['password']);
        $validated['username']       = make_username($validated['first_name'],$validated['last_name']);
        $validated['firstname']      = $validated['first_name'];
        $validated['lastname']       = $validated['last_name'];


        if(empty($validated['company_name'])){
            return back()->with(['error' => ['Company Name Field Is Required']]);
        }

        $validated['address']       = [
            'country'      => $validated['country'] ?? null,
            'city'         => $validated['city'] ?? null,
            'state'        => $validated['state'] ?? null,
            'address'      => $validated['address'] ?? null,
            'zip_code'     => $validated['zip_code'] ?? null,
            'company_name' => $validated['company_name'],
        ];

        event(new Registered($user = $this->create($validated)));

        return $this->registered($request, $user);
    }


    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    public function validator(array $data) {

        $basic_settings = $this->basic_settings;
        $passowrd_rule = "required|string|min:6";

        if($basic_settings->secure_password) {
            $passowrd_rule = ["required",Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()];
        }

        if (@$basic_settings->agree_policy == 1){
            $agree = 'required';
        }

        return Validator::make($data,[
            'first_name'   => 'required|string|max:60',
            'last_name'    => 'required|string|max:60',
            'company_name' => 'required|string|max:180',
            'country'      => 'required|string',
            'email'        => 'required|string|email|max:150|unique:users,email',
            'password'     => $passowrd_rule,
            'agree'        => $agree,
        ]);
    }


    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \App\Models\User
     */
    protected function create(array $data)
    {
        return User::create($data);
    }


    /**
     * The user has been registered.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function registered(Request $request, $user)
    {
        $this->createUserWallets($user);

        $this->guard()->login($user);

        return redirect()->intended(route('user.dashboard'));
    }
}
