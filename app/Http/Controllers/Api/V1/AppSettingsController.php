<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Admin\AppSettings;
use App\Models\Admin\BasicSettings;
use App\Http\Controllers\Controller;
use App\Models\Admin\AppOnboardScreens;
use App\Http\Resources\SplashScreenResource;
use App\Http\Resources\OnboardScreenResource;
use App\Http\Helpers\Api\Helpers as ApiResponse;
use App\Models\Admin\ExchangeRate;
use Illuminate\Support\Facades\Route;

class AppSettingsController extends Controller
{

    /**
     * Basic Settings Data Fetch
     *
     * @method GET
     * @return \Illuminate\Http\Response
    */

    public function basicSettings()
    {
        $image_path      = get_files_public_path('app-images');
        $logo_image_path = get_files_public_path('image-assets');
        $default_logo    = get_files_public_path('default');
        $onboard_screen  = OnboardScreenResource::collection(AppOnboardScreens::where('status', 1)->get());
        $splash_screen   = new SplashScreenResource(AppSettings::first());
        $all_logo        = BasicSettings::select('site_logo_dark', 'site_logo', 'site_fav_dark', 'site_fav')->first();


        $exchange_rate = ExchangeRate::where('status', 1)->get()->map(function($data){
            return [
                'id'              => $data->id,
                'name'            => $data->name,
                'mobile_code'     => $data->mobile_code,
                'currency_name'   => $data->currency_name,
                'currency_code'   => $data->currency_code,
                'currency_symbol' => $data->currency_symbol,
                'flag'            => $data->flag,
                'rate'            => $data->rate,
                'status'          => $data->status,
            ];
        });



        $data = [
            'default_logo'    => $default_logo,
            'logo_image_path' => $logo_image_path,
            'image_path'      => $image_path,
            'onboard_screen'  => $onboard_screen,
            'splash_screen'   => $splash_screen,
            'web_links'       => [
                'privacy-policy' => url('/page/privacy-policy'),
                'about-us'       => Route::has('about-us') ? route('about-us') : url('/'),
                'contact-us'     => Route::has('contact.us') ? route('contact.us') : url('/'),
            ],
            'all_logo'      => $all_logo,
            'exchange_rate' => $exchange_rate,
        ];
        $message = ['success' =>  [__('Data fetched successfully')]];
        return ApiResponse::success($message, $data);
    }
}
