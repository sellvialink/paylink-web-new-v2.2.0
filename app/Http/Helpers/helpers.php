<?php

use Twilio\Rest\Client;
use App\Models\UserWallet;
use App\Models\Admin\Admin;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use App\Constants\GlobalConst;
use App\Models\Admin\Language;
use App\Constants\LanguageConst;
use App\Models\Admin\GatewayAPi;
use App\Models\UserNotification;
use App\Constants\AdminRoleConst;
use App\Constants\ExtensionConst;
use App\Models\UserAuthorization;
use Illuminate\Http\UploadedFile;
use App\Models\Admin\AdminHasRole;
use App\Models\Admin\ExchangeRate;
use App\Models\Admin\SiteSections;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\App;
use App\Constants\NotificationConst;
use App\Models\Admin\PaymentGateway;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use App\Constants\SupportTicketConst;

use Illuminate\Support\Facades\Route;
use Intervention\Image\Facades\Image;
use App\Constants\PaymentGatewayConst;
use Buglinjo\LaravelWebp\Facades\Webp;
use App\Models\Admin\AdminNotification;
use App\Providers\Admin\CurrencyProvider;
use function PHPUnit\Framework\returnSelf;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Validation\ValidationException;
use App\Notifications\User\Auth\SendAuthorizationCode;

function setRoute($route_name, $param = null)
{
    if (Route::has($route_name)) {
        if ($param) {
            return route($route_name, $param);
        } else {
            return route($route_name);
        }
    } else {
        if (env('APP_ENV') != 'production') {
            if ($param) {
                return route($route_name, $param);
            } else {
                return route($route_name);
            }
        }
        return "javascript:void(0)";
    }
}

function get_all_countries($item = [])
{
    $countries = json_decode(file_get_contents(resource_path('world/countries.json')), true);

    $countries = array_map(function ($array) {
        return [
            'id'                    => $array['id'],
            'name'                  => $array['name'],
            'mobile_code'           => $array['phone_code'],
            'currency_name'         => $array['currency_name'],
            'currency_code'         => $array['currency'],
            'currency_symbol'       => $array['currency_symbol'],
            'iso2'                  => $array['iso2'],
        ];
    }, $countries);

    return json_decode(json_encode($countries));
}

function get_country_phone_code($country) {
    $countries = json_decode(file_get_contents(resource_path('world/countries.json')), true);
    $phone_code = "";
    foreach($countries as $item) {
        if($item['name'] == $country) {
            $phone_code = $item['phone_code'];
        }
    }
    if($phone_code == "") {
        throw new Exception("Sorry, country (" . $country . ") is not available in our list");
    }
    $phone_code = str_replace("+","",$phone_code);
    return $phone_code;
}

function get_currency_code($currency) {
    $countries = json_decode(file_get_contents(resource_path('world/countries.json')), true);
    $currency_data = [];

    foreach($countries as $item) {
        if($item['currency'] == $currency) {
            $currency_data = [
                'currency_name' => $item['currency_name'],
                'currency_code' => $item['currency'],
                'country' => $item['name'],
                'currency_symbol' => $item['currency_symbol'],
            ];
        }
    }
    return $currency_data;
}

function get_all_timezones()
{
    $countries = json_decode(file_get_contents(resource_path('world/countries.json')), true);

    $timezones = array_map(function ($array) {
        return [
            'name'  => $array['timezones'][0]['zoneName'],
        ];
    }, $countries);

    return json_decode(json_encode($timezones));
}

function get_country_states($country_id)
{

    $all_states = json_decode(file_get_contents(resource_path('world/states.json')), true);
    $states = [];

    foreach ($all_states as $item_array) {
        if (array_key_exists($item_array['country_id'], $all_states)) {
            if ($item_array['country_id'] == $country_id) {
                $states[] = [
                    'country_id'    => $item_array['country_id'],
                    'name'          => $item_array['name'],
                    'id'            => $item_array['id'],
                    'state_code'    => $item_array['state_code'],
                ];
            }
        }
    }

    return $states;
}

function get_state_cities($state_id)
{
    $all_cities = json_decode(file_get_contents(resource_path('world/cities.json')), true);

    $cities = [];

    foreach ($all_cities as $item_array) {
        if (array_key_exists($item_array['state_id'], $all_cities)) {
            if ($item_array['state_id'] == $state_id) {
                $cities[] = [
                    'name'          => $item_array['name'],
                    'id'            => $item_array['id'],
                    'state_code'    => $item_array['state_code'],
                    'state_name'    => $item_array['state_name'],
                ];
            }
        }
    }

    return $cities;
}

function get_files_from_fileholder($request, $file_input_name)
{
    $keyword = "fileholder";
    $fileholder_stored_file_path = public_path('fileholder/img');

    $files_link = [];
    if ($request->hasFile($file_input_name)) {
        $input_name = $keyword . "-" . $file_input_name;
        $file_name_array = explode(',', $request->$input_name);

        foreach ($file_name_array as $item) {
            $file_link = $fileholder_stored_file_path . "/" . $item;
            if (File::isFile($file_link)) {
                array_push($files_link, $file_link);
            } else {
                throw ValidationException::withMessages([
                    $file_input_name => "Uploaded file is not a proper file. Please upload valid file.",
                ]);
            }
        }
    } else {
        throw ValidationException::withMessages([
            $file_input_name => $file_input_name . " is required.",
        ]);
    }

    return $files_link;
}

function delete_files_from_fileholder(array $files_link)
{
    foreach($files_link as $item) {
        delete_file($item);
    }
    return true;
}

function upload_files_from_path_dynamic($files_path, $destination_path, $old_files = null)
{
    $output_files_name = [];
    foreach ($files_path as $path) {
        $file_name      = File::name($path);
        $file_extension = File::extension($path);
        $file_base_name = $file_name . "." . $file_extension;
        $file_mime_type = File::mimeType($path);
        $file_size      = File::size($path);

        $save_path = get_files_path($destination_path);

        $file_mime_type_array = explode('/', $file_mime_type);
        if (array_shift($file_mime_type_array) == "image" && $file_extension != "svg") { // If Image

            $file = Image::make($path)->orientate();

            $width = $file->width();
            $height = $file->height();

            $resulation_break_point = [2048, 2340, 2730, 3276, 4096, 5460, 8192];
            $reduce_percentage = [12.5, 25, 37.5, 50, 62.5, 75];

            // Dynamically Image Resizing & Move to Targeted folder
            if ($width > 0 && $width < 2048) {
                $new_width = $width;
                try {
                    $file->resize($new_width, null, function ($constraint) {
                        $constraint->aspectRatio();
                    })->save($path, 70);
                } catch (\Exception $e) {
                    return back()->with(['error' => ['Image Upload Failed!']]);
                }
            }
            if ($width > 5460 && $width <= 6140) {
                $new_width = 2048;
                try {
                    $file->resize($new_width, null, function ($constraint) {
                        $constraint->aspectRatio();
                    })->save($path, 70);
                } catch (\Exception $e) {
                    return back()->with(['error' => ['Image Upload Failed!']]);
                }
            } else {
                for ($i = 0; $i < count($resulation_break_point); $i++) {
                    if ($i != count($resulation_break_point) - 1) {
                        if ($width >= $resulation_break_point[$i] && $width <= $resulation_break_point[$i + 1]) {
                            $new_width = ceil($width - (($width * $reduce_percentage[$i]) / 100));
                            try {
                                $file->resize($new_width, null, function ($constraint) {
                                    $constraint->aspectRatio();
                                })->save($path, 70);
                            } catch (\Exception $e) {
                                return back()->with(['error' => ['Image Upload Failed!']]);
                            }
                        }
                    }
                }
                if ($width > 8192) {
                    $new_width = 2048;
                    try {
                        $file->resize($new_width, null, function ($constraint) {
                            $constraint->aspectRatio();
                        })->save($path, 70);
                    } catch (\Exception $e) {
                        return back()->with(['error' => ['Image Upload Failed!']]);
                    }
                }
            }

            $file_instance = new UploadedFile(
                $path,
                $file_base_name,
                $file_mime_type,
                $file_size,
            );

            $store_file_name = $file_name . ".webp";
            try {
                if ($file_extension != "webp") {
                    $webp = Webp::make($file_instance)->save($save_path . "/" . $store_file_name);
                    array_push($output_files_name, $store_file_name);
                } else {
                    File::move($file_instance, $save_path . "/" . $file_base_name);
                    array_push($output_files_name, $file_base_name);
                }
            } catch (Exception $e) {
                return back()->with(['error' => ['Something went worng! Faild to upload file.']]);
            }
        } else { // IF Other Files
            $file_instance = new UploadedFile(
                $path,
                $file_base_name,
                $file_mime_type,
                $file_size,
            );

            try {
                File::move($file_instance, $save_path . "/" . $file_base_name);
                array_push($output_files_name, $file_base_name);
            } catch (Exception $e) {
                return back()->with(['error' => ['Something went worng! Faild to upload file.']]);
            }
        }

        // Delete Old Files if exists
        try {
            if ($old_files) {
                if (is_array($old_files)) {
                    // Delete Multiple File
                    foreach ($old_files as $item) {
                        $file_link = $save_path . "/" . $item;
                        delete_file($item);
                    }
                } else if (is_string($old_files)) {
                    // Delete Single File
                    $file_link = $save_path . "/" . $old_files;
                    delete_file($file_link);
                }
            }
        } catch (Exception $e) {
            return back()->with(['error' => ['Something went worng! Faild to delete old file.']]);
        }
    }

    if (count($output_files_name) == 1) {
        return $output_files_name[0];
    }
    // delete_files_from_fileholder($output_files_name);
    return $output_files_name;
}

function get_files_path($slug)
{
    $data = files_path($slug);
    $path = $data->path;
    create_asset_dir($path);
    return public_path($path);
}

function create_asset_dir($path)
{
    $path = "public/" . $path;
    if (file_exists($path)) return true;
    return mkdir($path, 0755, true);
}

function get_image($image_name, $path_type = null, $image_type = null, $size = null)
{

    if ($image_type == 'profile') {
        $image =  asset('public/' . files_path('profile-default')->path);
    } else {
        $image =  asset('public/' . files_path('default')->path);
    }
    if ($image_name != null) {
        if ($path_type != null) {
            $image_path = files_path($path_type)->path;
            $image_link = $image_path . "/" . $image_name;
            if (file_exists(public_path($image_link))) {
                $image = asset('public/' . $image_link);
            }
        }
    }

    return $image;
}

function get_storage_image($image_name, $path_type = null, $image_type = null, $size = null)
{

    if ($image_type == 'profile') {
        $image =  asset(files_path('profile-default')->path);
    } else {
        $image =  asset(files_path('default')->path);
    }
    if ($image_name != null) {
        if ($path_type != null) {
            $image_path = files_path($path_type)->path;
            $image_link = $image_path . "/" . $image_name;

            if (file_exists(storage_path($image_link))) {
                // if(file_exists(public_path($image_link))) {
                $image = asset($image_link);
            }
        }
    }

    return $image;
}

function files_path($slug)
{
    $data = [
        'admin-profile'         => [
            'path'              => 'backend/images/admin/profile',
            'width'             => 800,
            'height'            => 800,
        ],
        'default'               => [
            'path'              => 'backend/images/default/default.webp',
            'width'             => 800,
            'height'            => 800,
        ],
        'profile-default'       => [
            'path'              => 'backend/images/default/profile-default.webp',
            'width'             => 800,
            'height'            => 800,
        ],
        'currency-flag'         => [
            'path'              => 'backend/images/currency-flag',
            'width'             => 400,
            'height'            => 400,
        ],
        'payment-link-image'         => [
            'path'              => 'backend/images/payment-link-image',
            'width'             => 400,
            'height'            => 400,
        ],
        'products'         => [
            'path'              => 'backend/images/products',
            'width'             => 400,
            'height'            => 400,
        ],
        'image-assets'          => [
            'path'              => 'backend/images/web-settings/image-assets',
        ],
        'seo'                   => [
            'path'              => 'backend/images/seo',
        ],
        'app-images'            => [
            'path'              => 'backend/images/app',
            'width'             => 414,
            'height'            => 896,
        ],
        'campaigns'            => [
            'path'              => 'backend/images/campaigns',
            'width'             => 414,
            'height'            => 896,
        ],
        'web-journal'            => [
            'path'              => 'backend/images/web-journal',
            'width'             => 631,
            'height'            => 388,
        ],
        'payment-gateways'      => [
            'path'              => 'backend/images/payment-gateways',
        ],
        'extensions'      => [
            'path'              => 'backend/images/extensions',
        ],
        'user-profile'      => [
            'path'              => 'frontend/user',
        ],
        'language-file'     => [
            'path'          => 'backend/files/language',
        ],
        'site-section'         => [
            'path'          => 'frontend/images/site-section',
        ],
        'support-attachment'    => [
            'path'          => 'frontend/images/support-ticket/attachment',
        ],
        'kyc-files'         => [
            'path'          => 'backend/files/kyc-files'
        ],
        'junk-files'        => [
            'path'      => 'backend/files/junk-files',
        ],
    ];

    return (object) $data[$slug];
}

function files_asset_path($slug)
{
    $files_path = files_path($slug)->path;
    return asset('public/' . $files_path);
}

function get_amount($amount, $currency = null, $precision = null)
{
    if (!is_numeric($amount)) return "Not Number";
        $amount = ($precision) ? number_format($amount, $precision, ".", ",") : number_format($amount, 2, ".", ",");
    if (!$currency) return $amount;
    $amount = $amount . " " . $currency;
    return $amount;
}

function getDynamicAmount($amount, $currency = null)
{
    if (!is_numeric($amount)) return "Not Number";

    $amount = doubleval($amount);

    if (strpos($amount, '.') !== false) {
        $amount = rtrim(rtrim($amount, '0'), '.');
        $explode = explode('.', $amount);
        if(strlen($explode[1]) == 1){
            $amount = number_format($amount, 2, ".", "");
        }
    } else {
        $amount = $amount . '.00';
    }

    if (!$currency) return $amount;
    $amount = $amount . " " . $currency;
    return $amount;
}


function get_logo($basic_settings, $type = null)
{
    $logo = "";
    if ($type == 'white') {
        if (!$basic_settings->site_logo) {
            $logo = files_asset_path('default');
        } else {
            $logo = files_asset_path('image-assets') . "/" . $basic_settings->site_logo;
        }
    }

    if ($type == 'dark') {
        if (!$basic_settings->site_logo_dark) {
            $logo = files_asset_path('default');
        } else {
            $logo = files_asset_path('image-assets') . "/" . $basic_settings->site_logo_dark;
        }
    }

    if ($type == null) {
        if (!$basic_settings->site_logo) {
            if (!$basic_settings->site_logo_dark) {
                $logo = files_asset_path('default');
            } else {
                $logo = files_asset_path('image-assets') . "/" . $basic_settings->site_logo_dark;
            }
        } else {
            $logo = files_asset_path('image-assets') . "/" . $basic_settings->site_logo;
        }
    }

    return $logo;
}

function get_logo_public_path($basic_settings, $type = null)
{
    $logo = "";
    if ($type == 'white') {
        if (!$basic_settings->site_logo) {
            $logo = get_files_path('default');
        } else {
            $logo = get_files_path('image-assets') . "/" . $basic_settings->site_logo;
        }
    }

    if ($type == 'dark') {
        if (!$basic_settings->site_logo_dark) {
            $logo = get_files_path('default');
        } else {
            $logo = get_files_path('image-assets') . "/" . $basic_settings->site_logo_dark;
        }
    }

    if ($type == null) {
        if (!$basic_settings->site_logo) {
            if (!$basic_settings->site_logo_dark) {
                $logo = get_files_path('default');
            } else {
                $logo = get_files_path('image-assets') . "/" . $basic_settings->site_logo_dark;
            }
        } else {
            $logo = get_files_path('image-assets') . "/" . $basic_settings->site_logo;
        }
    }

    return $logo;
}

function get_fav($basic_settings = null, $type = null)
{
    if(!$basic_settings) $basic_settings = BasicSettingsProvider::get();
    $fav = "";
    if ($type == 'white') {
        if (!$basic_settings->site_fav) {
            $fav = files_asset_path('default');
        } else {
            $fav = files_asset_path('image-assets') . "/" . $basic_settings->site_fav;
        }
    }

    if ($type == 'dark') {
        if (!$basic_settings->site_fav_dark) {
            $fav = files_asset_path('default');
        } else {
            $fav = files_asset_path('image-assets') . "/" . $basic_settings->site_fav_dark;
        }
    }

    if ($type == null) {
        if (!$basic_settings->site_fav) {
            if (!$basic_settings->site_fav_dark) {
                $fav = files_asset_path('default');
            } else {
                $fav = files_asset_path('image-assets') . "/" . $basic_settings->site_fav_dark;
            }
        } else {
            $fav = files_asset_path('image-assets') . "/" . $basic_settings->site_fav;
        }
    }

    return $fav;
}

function upload_files_from_path_static($files_path, $destination_path, $old_files = null, $crop = true, $compress = false, $crop_position = "center")
{
    $output_files_name = [];
    foreach ($files_path as $path) {
        $file_name      = File::name($path);
        $file_extension = File::extension($path);
        $file_base_name = $file_name . "." . $file_extension;
        $file_mime_type = File::mimeType($path);
        $file_size      = File::size($path);

        $save_path = get_files_path($destination_path);

        $file_mime_type_array = explode('/', $file_mime_type);
        if (array_shift($file_mime_type_array) == "image" && $file_extension != "svg") { // If Image

            $file = Image::make($path)->orientate();

            $width = $file->width();
            $height = $file->height();

            $resulation_break_point = [2048, 2340, 2730, 3276, 4096, 5460, 8192];
            $reduce_percentage = [12.5, 25, 37.5, 50, 62.5, 75];

            // Dynamically Image Resizing
            if ($compress === true) {
                if ($width > 0 && $width < 2048) {
                    $new_width = $width;
                    try {
                        $file->resize($new_width, null, function ($constraint) {
                            $constraint->aspectRatio();
                        });
                    } catch (\Exception $e) {
                        return back()->with(['error' => ['Image Upload Failed!']]);
                    }
                }
                if ($width > 5460 && $width <= 6140) {
                    $new_width = 2048;
                    try {
                        $file->resize($new_width, null, function ($constraint) {
                            $constraint->aspectRatio();
                        });
                    } catch (\Exception $e) {
                        return back()->with(['error' => ['Image Upload Failed!']]);
                    }
                } else {
                    for ($i = 0; $i < count($resulation_break_point); $i++) {
                        if ($i != count($resulation_break_point) - 1) {
                            if ($width >= $resulation_break_point[$i] && $width <= $resulation_break_point[$i + 1]) {
                                $new_width = ceil($width - (($width * $reduce_percentage[$i]) / 100));
                                try {
                                    $file->resize($new_width, null, function ($constraint) {
                                        $constraint->aspectRatio();
                                    });
                                } catch (\Exception $e) {
                                    return back()->with(['error' => ['Image Upload Failed!']]);
                                }
                            }
                        }
                    }
                    if ($width > 8192) {
                        $new_width = 2048;
                        try {
                            $file->resize($new_width, null, function ($constraint) {
                                $constraint->aspectRatio();
                            });
                        } catch (\Exception $e) {
                            return back()->with(['error' => ['Image Upload Failed!']]);
                        }
                    }
                }
            }

            // Crop Image
            if ($crop === true) {
                $image_settings = files_path('app-images');
                $crop_width     = $image_settings->width ?? false;
                $crop_height    = $image_settings->height ?? false;

                if ($crop_width != false && $crop_height != false) {
                    $file->fit($crop_width, $crop_height, null, $crop_position);
                }

                if ($crop_width != false && $crop_height == false) {
                    $file->resize($crop_width, null, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                }
            }

            // Save File
            try {
                $file->save($path, 70);
            } catch (Exception $e) {
                return back()->with(['error' => ['Something went worng! Faild to save file.']]);
            }

            $file_instance = new UploadedFile(
                $path,
                $file_base_name,
                $file_mime_type,
                $file_size,
            );

            $store_file_name = $file_name . ".webp";
            try {
                if ($file_extension != "webp") {
                    $webp = Webp::make($file_instance)->save($save_path . "/" . $store_file_name);
                    array_push($output_files_name, $store_file_name);
                } else {
                    File::move($file_instance, $save_path . "/" . $file_base_name);
                    array_push($output_files_name, $file_base_name);
                }
            } catch (Exception $e) {
                return back()->with(['error' => ['Something went worng! Faild to upload file.']]);
            }
        } else { // IF Other Files
            $file_instance = new UploadedFile(
                $path,
                $file_base_name,
                $file_mime_type,
                $file_size,
            );

            try {
                File::move($file_instance, $save_path . "/" . $file_base_name);
                array_push($output_files_name, $file_base_name);
            } catch (Exception $e) {
                return back()->with(['error' => ['Something went worng! Faild to upload file.']]);
            }
        }

        // Delete Old Files if exists
        try {
            if ($old_files) {
                if (is_array($old_files)) {
                    // Delete Multiple File
                    foreach ($old_files as $item) {
                        $file_link = $save_path . "/" . $item;
                        delete_file($item);
                    }
                } else if (is_string($old_files)) {
                    // Delete Single File
                    $file_link = $save_path . "/" . $old_files;
                    delete_file($file_link);
                }
            }
        } catch (Exception $e) {
            return back()->with(['error' => ['Something went worng! Faild to delete old file.']]);
        }
    }

    if (count($output_files_name) == 1) {
        return $output_files_name[0];
    }
    // delete_files_from_fileholder($output_files_name);
    return $output_files_name;
}

function delete_file($file_link)
{
    if (File::exists($file_link)) {
        try {
            File::delete($file_link);
        } catch (Exception $e) {
            return false;
        }
    }
    return true;
}
function get_default_currency_rate($default_currency_rate = null)
{
    if($default_currency_rate == null) $default_currency_rate = CurrencyProvider::default();
    if ($default_currency_rate != false) {
        return $default_currency_rate->rate;
    }
    return 0.00;
}
function get_default_currency_code($default_currency = null)
{
    if($default_currency == null) $default_currency = CurrencyProvider::default();
    if ($default_currency != false) {
        return $default_currency->code;
    }
    return "";
}
function get_default_currency_symbol($default_currency = null)
{
    if($default_currency == null) $default_currency = CurrencyProvider::default();
    if ($default_currency != false) {
        return $default_currency->symbol;
    }
    return "";
}
function authWalletBalance(){
    $wallet = UserWallet::where('user_id',auth()->user()->id)->first();
    return number_format($wallet->balance,2);
}
function walletBalance(){
    $currency = CurrencyProvider::default();
    $wallet = UserWallet::where('user_id', Auth::user()->id)->where('currency_id', $currency->id)->first();
    return $wallet;
}
function replace_array_key($array, $remove_keyword, $replace_keyword = "")
{
    $filter = [];
    foreach ($array as $key => $value) {
        $update_key = preg_replace('/' . $remove_keyword . '/i', $replace_keyword, $key);
        $filter[$update_key] = $value;
    }
    return $filter;
}


function get_paginate($data)
{
    try {
        return $data->onEachSide(2)->links();
    } catch (Exception $e) {
        return "";
    }
}


function set_payment_gateway_code($last_record_of_code)
{
    return intval($last_record_of_code + 5);
}

function make_input_name($string)
{
    $string         = preg_replace('/[^A-Za-z0-9]/', ' ', $string);
    $string         = preg_replace("/ /i", "_", $string);
    $string         = Str::lower($string);
    return $string;
}

/**
 * Function for Making Input field array with all information that comes from Frontend Form
 * @param array $validated
 * @return array $input_fields
 */
function decorate_input_fields($validated)
{
    $input_fields = [];
    $field_necessity_list = [
        '1'             => true,
        '0'             => false,
    ];
    $file_array_key = 0;
    $select_array_key = 0;
    $global_array_key = 0;
    foreach ($validated['input_type'] ?? [] as $key => $item) {
        $field_necessity = $validated['field_necessity'][$key] ?? "";
        $validation_rules = ['min' => "0", 'mimes' => []];
        if ($item == "file") {
            $extensions = $validated['file_extensions'][$file_array_key] ?? "";
            $extensions = explode(",", $extensions);
            $validation_rules = [
                'max'       => $validated['file_max_size'][$file_array_key] ?? "0",
                'mimes'     => $extensions,
                'min'       => "0",
                'options'  => [],
            ];
            $file_array_key++;
        } else if ($item == "select") {
            $options = $validated['select_options'][$select_array_key] ?? "";
            $options = explode(",", $options);
            $validation_rules = [
                'max'       => "0",
                'min'       => "0",
                'mimes'     => [],
                'options'   => $options,
            ];
            $select_array_key++;
        } else {
            $validation_rules = [
                'max'      => $validated['max_char'][$global_array_key] ?? "0",
                'mimes'    => [],
                'min'      => $validated['min_char'][$global_array_key] ?? "0",
                'options'  => [],
            ];
            $global_array_key++;
        }
        $validation_rules['required'] = $field_necessity_list[$field_necessity] ?? false;
        $input_fields[]     = [
            'type'          => $item,
            'label'         => $validated['label'][$key] ?? "",
            'name'          => make_input_name($validated['label'][$key] ?? ""),
            'required'      => $field_necessity_list[$field_necessity] ?? false,
            'validation'    => $validation_rules,
        ];
    }
    return $input_fields;
}


/**
 * Function for replace ENV Value based on key
 * @param array $replace_array
 */
function modifyEnv($replace_array = [])
{

    $array_going_to_modify  = $replace_array;

    if (count($array_going_to_modify) == 0) {
        return false;
    }

    $env_file = App::environmentFilePath();
    $env_content = $_ENV;

    $update_array = ["APP_ENV" => App::environment()];

    foreach ($env_content as $key => $value) {
        foreach ($array_going_to_modify as $modify_key => $modify_value) {
            if ($key == $modify_key) {
                $update_array[$key] = '"'.$modify_value.'"';
                break;
            } else {
                $update_array[$key] = '"'.$value.'"';
            }
        }
    }

    $string_content = "";
    foreach ($update_array as $key => $item) {
        $line = $key . "=" . $item;
        $string_content .= $line . "\n\r";
    }

    sleep(2);

    file_put_contents($env_file, $string_content);
}

// Role Permission START

function permission_skip()
{
    return [
        'admin.logout',
        'admin.languages.switch',
        'admin.currency.search',
        'admin.notifications.clear',
        'admin.users.search',
        'admin.admins.search',
        'admin.users.sms.unverified'
    ];
}

function get_role_permission_routes()
{
    $routes_info = Route::getRoutes()->get();
    $routes_name = [];
    foreach ($routes_info as $key => $item) {
        if (isset($item->action['as'])) {
            if (Str::is("admin.*", $item->action['as'])) {
                if (Str::is("admin.login*", $item->action['as'])) {
                    continue;
                } else if (Str::is("admin.profile*", $item->action['as'])) {
                    continue;
                } else if (Str::is("admin.password*", $item->action['as'])) {
                    continue;
                } else if (in_array($item->action['as'], permission_skip())) {
                    continue;
                }
                $routes_name[] = $item->action['as'];
            }
        }
    }

    $readable_route_text = [];
    foreach ($routes_name as $item) {
        $make_title = str_replace('admin.', "", $item);
        $make_title = str_replace('.', " ", $make_title);
        $make_title = ucwords($make_title);
        $readable_route_text[] = [
            'route'     => $item,
            'text'      => $make_title,
        ];
    }

    return $readable_route_text;
}

function get_route_info($route_name)
{
    $route_info = Route::getRoutes()->getByName($route_name);
    return $route_info;
}

function system_super_admin()
{
    if (AdminHasRole::whereHas('role', function ($query) {
        $query->where("name", AdminRoleConst::SUPER_ADMIN);
    })->exists()) return true;
    return false;
}

function admin_role_const()
{
    return AdminRoleConst::class;
}

function auth_admin_roles()
{
    return auth()->guard("admin")->user()->getRolesCollection();
}

function auth_admin_permissions()
{
    $auth_admin_roles = Auth::user()->roles;
    $permissions = [];
    foreach ($auth_admin_roles as $item) {
        if ($item->permission != null && $item->permission->hasPermissions != null) {
            foreach ($item->permission->hasPermissions as $innerItem) {
                array_push($permissions, $innerItem->route);
            }
        }
    }
    return array_unique($permissions);
}

function auth_is_super_admin()
{
    $auth_admin_roles = auth_admin_roles();
    if (in_array(AdminRoleConst::SUPER_ADMIN, $auth_admin_roles)) return true;
    return false;
}

function permission_protected()
{
    $permissions = get_role_permission_routes();
    $permissions = Arr::pluck($permissions, ["route"]);
    return $permissions;
}

function auth_admin_incomming_permission()
{
    $incomming_access = Route::currentRouteName();
    $auth_admin_permissions = auth_admin_permissions();
    if (auth_is_super_admin() == true) return true;
    if (!in_array($incomming_access, permission_protected())) return true;
    if (in_array($incomming_access, $auth_admin_permissions)) return true;
    return false;
}

function admin_permission_by_name($name)
{
    if (auth_is_super_admin()) return true;
    if (in_array($name, auth_admin_permissions())) return true;
    return false;
}

function auth_has_no_role()
{
    if (count(auth_admin_roles()) == 0) {
        return true;
    }
    return false;
}

function auth_has_role()
{
    if (count(auth_admin_roles()) > 0) {
        return true;
    }
    return false;
}


function admin_permission_by_name_array($names)
{
    $auth_admin_permissions = auth_admin_permissions();
    if (auth_is_super_admin()) return true;
    $match = array_intersect($auth_admin_permissions, $names);
    if (count($match) > 0) {
        return true;
    }
    return false;
}

// Role Permission END
function remove_spaces($string)
{
    return str_replace(' ', "", $string);
}


function get_admin_notifications()
{
    $admin = auth()->user();
    $notification_clear_at =   $admin->notification_clear_at;
    if ($notification_clear_at  == null) {
        $notifications = AdminNotification::notAuth()->getByType([NotificationConst::SIDE_NAV])->get();
    } else {
        $notifications = AdminNotification::notAuth()->getByType([NotificationConst::SIDE_NAV])->where(function ($query) use ($notification_clear_at) {
            $query->where("created_at", ">", $notification_clear_at);
        })->select('message', 'created_at', 'type')->get();
    }

    return $notifications;
}

function language_const()
{
    return LanguageConst::class;
}

function addMoneyChargeCalc($amount, $charges)
{
    $rate = $charges->rate ?? 0;
    if ($charges != null) {
        $fixed_charges = $charges->fixed_charge;
        $percent_charges = $charges->percent_charge;
    } else {
        $fixed_charges = 0;
        $percent_charges = 0;
    }
    $fixed_charge_calc = ($rate * $fixed_charges);
    $percent_charge_calc = ($amount / 100) * $percent_charges;
    $total_charge = $fixed_charge_calc + $percent_charge_calc;
    $total_amount = $amount + $total_charge;
    $data = [
        'requested_amount'  => $amount,
        'total_amount'      => $total_amount,
        'total_charges'     => $total_charge,
        'fixed_charge'      => $fixed_charge_calc,
        'percent_charges'   => $percent_charge_calc,
    ];
    return (object) $data;
}

function create_file($path, $mode = "w")
{
    return fopen($path, $mode);
}


function get_first_file_from_dir($dir) {
    $files = scandir($dir);
    if(is_array($files) && count($files) > 2) return $files[2];
    return false;
}

function language_file_exists() {
    $file_path = get_files_path('language-file');
    $files = scandir($file_path);
    if(is_array($files) && count($files) > 2) return true;
    return false;
}

function get_default_language_code() {
    $default_language = Language::where('status',GlobalConst::ACTIVE)->first();
    $default_language_code = $default_language->code ?? LanguageConst::NOT_REMOVABLE;
    return session()->get('local')?? $default_language_code;
}

function get_admin($username) {
    $admin = Admin::where("username",$username)->first();
    return $admin;
}

function setPageTitle(string $title) {
    $basic_settings = BasicSettingsProvider::get();
    return $basic_settings->site_name . " | " . $title;
}

function make_username($first_name,$last_name,$table = "users") {
    // Make username Dynamically
    $generate_name_with_count = "";
    do{
        // Generate username
        $firstName = $first_name;
        $lastName = $last_name;

        if($generate_name_with_count == "") {
            if(strlen($firstName) >= 6) {
                $generate_name = filter_string_lower($firstName);
            }else {
                $modfy_last_name = explode(' ',$lastName);
                $lastName = filter_string_lower($modfy_last_name[0]);
                $firstName = filter_string_lower($firstName);
                $generate_name = $firstName . $lastName;
                if(strlen($generate_name) < 6) {
                    $firstName = filter_string_lower($firstName);
                    $lastName = filter_string_lower($lastName);
                    $generate_name = $firstName . $lastName;

                    if(strlen($generate_name) < 6) {
                        $getCurrentLen = strlen($generate_name);
                        $dueChar = 6 - $getCurrentLen;
                        $generate_due_char = strtolower(generate_random_string($dueChar));
                        $generate_name = $generate_name . $generate_due_char;
                    }
                }
            }
        }else {
            $generate_name = $generate_name_with_count;
        }

        // Find User is already exists or not
        $chekUser = DB::table($table)->where('username',$generate_name)->first();

        if($chekUser == null) {
            $loop = false;
        }else {
            $generate_name_with_count = $generate_name;

            $split_string = array_reverse(str_split($generate_name_with_count));
            $username_string_part = "";
            $last_numeric_values = "";
            $numeric_close = false;

            foreach($split_string as $character) {
                if($numeric_close == false) {
                    if(is_numeric($character)) {
                        $last_numeric_values .= $character;
                    }else {
                        $numeric_close = true;
                    }
                }
                if($numeric_close == true) {
                    $username_string_part .= $character;
                }
            }

            if($last_numeric_values == "") { // If has no number in username string;
                $last_numeric_values = 1;
            }

            $username_string_part = strrev($username_string_part); // usernaem back to reverse;
            $last_numeric_values = strrev($last_numeric_values); // last number back to reverse;
            $generate_name_with_count = $username_string_part . ($last_numeric_values + 1);
            $loop = true;
        }
    }while($loop);

    return $generate_name;
}

function filter_string_lower($string) {
    $username = preg_replace('/ /i','',$string);
    $username = preg_replace('/[^A-Za-z0-9\-]/', '', $username);
    $username = strtolower($username);
    return $username;
}

function generate_random_string($length = 12)
{
    $characters = 'ABCDEFGHJKMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}
function generate_random_number($length = 12)
{
    $numbers = '0123456789';
    $numbersLength = strlen($numbers);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $numbers[rand(0, $numbersLength - 1)];
    }
    return $randomString;
}


function generate_random_string_number($length = 12)
{
    $characters = 'ABCDEFGHJKMNOPQRSTUVWXYZ123456789abcdefghijklmnopqrstuvwxyz';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}

function generate_unique_string($table,$column,$length = 10, $case = null) {
    do{
       $generate_rand_string = generate_random_string_number($length);
       if($case){
        $generate_rand_string = strtoupper($generate_rand_string);
       }
       $unique = DB::table($table)->where($column,$generate_rand_string)->exists();
       $loop = false;
       if($unique) {
        $loop = true;
       }
       $unique_string = $generate_rand_string;
    }while($loop);

    return $unique_string;
}

function upload_file($file,$destination_path,$old_file = null) {
    if(File::isFile($file)) {
        $save_path = get_files_path($destination_path);
        $file_extension = $file->getClientOriginalExtension();
        $file_type = File::mimeType($file);
        $file_size = File::size($file);
        $file_original_name = $file->getClientOriginalName();

        $file_base_name = explode(".",$file_original_name);
        array_pop($file_base_name);
        $file_base_name = implode("-",$file_base_name);

        $file_name = Str::uuid() . "." . $file_extension;

        $file_public_link   = $save_path . "/" . $file_name;
        $file_asset_link    = files_asset_path($destination_path) . "/" . $file_name;

        $file_info = [
            'name'                  => $file_name,
            'type'                  => $file_type,
            'extension'             => $file_extension,
            'size'                  => $file_size,
            'file_link'             => $file_asset_link,
            'dev_path'              => $file_public_link,
            'original_name'         => $file_original_name,
            'original_base_name'    => $file_base_name,
        ];

        try{

            if($old_file) {
                $old_file_link = $save_path . "/" . $old_file;
                delete_file($old_file_link);
            }

            File::move($file,$file_public_link);
        }catch(Exception $e) {
            return false;
        }

        return $file_info;
    }

    return false;
}

function delete_files($files_link)
{
    if(is_array($files_link)) {
        foreach($files_link as $item) {
            if (File::exists($item)) {
                try {
                    File::delete($item);
                } catch (Exception $e) {
                    // return false;
                }
            }
        }
    }
}

function support_ticket_const() {
    return SupportTicketConst::class;
}

function get_percentage_from_two_number($total,$available,$result_type = "int") {
    if(is_numeric($total) && is_numeric($available)) {
        $one_percent = $total / 100;
        $result = 0;
        if($one_percent > 0) $result = $available / $one_percent;
        if($result_type == "int") return (int) ceil($result);
        return number_format($result, 2, ".", ",");
    }
}

function remove_speacial_char($string) {
    return preg_replace("/[^A-Za-z0-9]/","",$string);
}

function check_email($string) {
    if(filter_var($string,FILTER_VALIDATE_EMAIL)) {
        return true;
    }
    return false;
}

function generate_random_code($length = 6) {
    $numbers = '123456789';
    $numbersLength = strlen($numbers);
    $randNumber = '';
    for ($i = 0; $i < $length; $i++) {
        $randNumber .= $numbers[rand(0, $numbersLength - 1)];
    }
    return $randNumber;
}

function mailVerificationTemplate($user) {
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
        Auth::logout();
        return redirect()->back()->with(['error' => ['Oops! Something went wrong! ('.$e->getMessage().')']]);
    }
    return redirect()->route('user.authorize.mail',$data['token'])->with(['warning' => ['Please verify your mail address. Check your mail inbox to get verification code']]);
}

function smsVerificationTemplate($user) {
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

        $message = 'Your '.env('APP_NAME').' verification code is'.$data['code'];

        $basic_settings = BasicSettingsProvider::get();

        if ($basic_settings->sms_config->name == 'twilio') {
            sendTwilioMessage($message, $user->full_mobile, $basic_settings);
        }

        DB::commit();
    }catch(Exception $e) {
        DB::rollBack();
        return back()->with(['error' => [__('Something Went Wrong! Please Try Again.')]]);
    }

    return redirect()->route('user.authorize.sms',$data['token'])->with(['warning' => ['Please verify your phone number. Check your phone to get verification code']]);
}

/**
 * Sends sms to user using Twilio's programmable sms client
 * @param String $message Body of sms
 * @param Number $recipients string or array of phone number of recepient
 */
function sendTwilioMessage($message, $recipients, $basic_settings)
{
    $account_sid   = $basic_settings->sms_config->twilio_account_sid;
    $auth_token    = $basic_settings->sms_config->twilio_auth_token;
    $twilio_number = $basic_settings->sms_config->twilio_from_number;

    $client = new Client($account_sid, $auth_token);
    $client->messages->create('+'.$recipients,['from' => $twilio_number, 'body' => $message] );
}

function extension_const() {
    return ExtensionConst::class;
}

function global_const() {
    return GlobalConst::class;
}

function imageExtenstions() {
    return ['png','jpg','jpeg','svg','webp','gif'];
}

function its_image(string $string) {
    if(!is_string($string)) return false;
    $extension = explode(".",$string);
    $extension = strtolower(end($extension));
    if(in_array($extension,imageExtenstions())) return true;
    return false;
}

function get_file_link($path_source, $name = null) {
    if($name == null) return false;
    $path = files_asset_path($path_source);
    $link = $path . "/" . $name;
    $dev_link = get_files_path($path_source) . "/" . $name;
    if(is_file($dev_link)) return $link;
    return false;
}

function get_file_basename_ext_from_link(string $link) {
    $link = $link;
    $file_name = explode("/",$link);
    $file_name = end($file_name);
    $file_base = explode(".",$file_name);
    $extension = end($file_base);
    array_pop($file_base);
    $file_base = implode(".",$file_base);
    return (object) ['base_name' => $file_base, 'extension' => $extension];
}
function menuActive($routeName, $type = null)
{
    $class = 'active';
    if (is_array($routeName)) {
        foreach ($routeName as $key => $value) {
            if (request()->routeIs($value)) {
                return $class;
            }
        }
    } elseif (request()->routeIs($routeName)) {
        return $class;
    }
}
function slug($string)
{
    return Illuminate\Support\Str::slug($string);
}
function getSectionData($slug){
    $data = SiteSections::where('key', $slug)->first();
    return $data;
}
if(!function_exists('dateFormat')){
    function dateFormat($format, $date){
        return date($format, strtotime($date));
    }
}
function payment_gateway_const() {
    return PaymentGatewayConst::class;
}
function get_gateway_image($gateway_id){
    $gateway = PaymentGateway::where('id',$gateway_id)->first();
    $image = get_image($gateway->image,"payment-gateways");
    return $image;
}
function generateTrxString($table,$column,$prefix = '',$length = 8) {
    do{
       $generate_number = generate_random_number($length);
       $generate_number = $prefix.''.$generate_number;
       $unique = DB::table($table)->where($column,$generate_number)->exists();
       $loop = false;
       if($unique) {
        $loop = true;
       }
       $unique_number = $generate_number;
    }while($loop);

    return $unique_number;
}
if (!function_exists('formatNumberInKNotation')) {
    function formatNumberInKNotation (Int $number, Int $decimals = 1) : String {
        # Define the unit size and supported units.
        $unitSize = 1000;
        $units = ["", "K", "M", "B", "T"];
        $unitsCount = ($number === 0) ? 0 : floor(log(abs($number), $unitSize));
        $unit = $units[min($unitsCount, count($units) - 1)];
        $value = round($number / pow($unitSize, $unitsCount), $decimals);
        return $value . $unit;
    }
}
if (!function_exists('formatNumberInKNotationValue')) {
    function formatNumberInKNotationValue (Int $number, Int $decimals = 1) : String {
        # Define the unit size and supported units.
        $unitSize = 1000;
        $units = ["", "K", "M", "B", "T"];
        $unitsCount = ($number === 0) ? 0 : floor(log(abs($number), $unitSize));
        $value = round($number / pow($unitSize, $unitsCount), $decimals);
        return $value;
    }
}
if (!function_exists('formatNumberInKNotationUnit')) {
    function formatNumberInKNotationUnit (Int $number, Int $decimals = 1) : String {
        # Define the unit size and supported units.
        $unitSize = 1000;
        $units = ["", "K", "M", "B", "T"];
        $unitsCount = ($number === 0) ? 0 : floor(log(abs($number), $unitSize));
        $unit = $units[min($unitsCount, count($units) - 1)];
        return $unit;
    }
}
function getAmount($amount, $length = 8)
{
    $amount = round($amount, $length);
    return $amount + 0;
}
function get_files_public_path($slug)
{
    $files_path = files_path($slug)->path ?? "";
    return "public/" . $files_path;
}
function get_auth_guard() {
    if(auth()->guard("web")->check()) {
        return "web";
    }else if(auth()->guard("admin")->check()) {
        return "admin";
    }else{
        return "api";
    }
    return "";
}

function fullName($first_name, $last_name){
    return $first_name.' '.$last_name ?? '';
}

function feesAndChargeCalculation($intervals, $rate){
    foreach ($intervals as $key => $value) {
        if ($value->min_limit <= $rate && $value->max_limit >= $rate) {
            $fixed_charge_calc   = $value->charge;
            $percent_charge_calc = ($rate * $value->percent) / 100;
            $total_charge = $fixed_charge_calc + $percent_charge_calc;
        }
    }

    $data = [
        'fixed_charge' =>  (double) $fixed_charge_calc,
        'percent_charge' => (double) $percent_charge_calc,
        'total_charge' => (double) $total_charge,
    ];

    return $data;
}

// Googele 2FA
function generate_google_2fa_auth_qr() {
    $google2FA = new \PragmaRX\Google2FA\Google2FA();
    $secret_key = $google2FA->generateSecretKey();
    $user = auth()->user();
    $site_url = App::make('url')->to('/');
    if($user->two_factor_secret) {
        $generate_text = $google2FA->getQRCodeUrl($site_url,$user->username,$user->two_factor_secret);
    }else {
        $generate_text = $google2FA->getQRCodeUrl($site_url,$user->username,$secret_key);
        $user->update([
            'two_factor_secret' => $secret_key,
        ]);
    }
    // $qr_image = 'https://chart.googleapis.com/chart?cht=qr&chs=350x350&chl='.$generate_text;
    $qr_image = 'https://qrcode.tec-it.com/API/QRCode?data='.$generate_text;
    return $qr_image;
}
function google_two_factor_verification_user_template($user) {
    return redirect()->route('user.authorize.google.2fa')->with(['error' => ['Please verify two factor authentication']]);
}
function google_2fa_verify($secret_key,$code) {
    $google2FA = new \PragmaRX\Google2FA\Google2FA();

    if($google2FA->verifyKey($secret_key, $code,0) == false) {
        if(request()->expectsJson()) return false;
        throw ValidationException::withMessages([
            'code'       => "Invalid authentication code",
        ]);
        return false;
    }
    return true;
}

function generateQr($val)
{
    return "https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl=$val&choe=UTF-8&chf=bg,s,FFFFFFFF";
}

function get_user_notifications()
{
    $notifications = UserNotification::auth()->latest('id')->take(4)->get();
    return $notifications;
}

function formatNumberWithLeadingZero($number = 0) {
    if ($number < 10) {
        return "0" . $number;
    } else {
        return $number;
    }
}

function getUserDefaultCurrency()
{
    $exchange_rate = ExchangeRate::where('name', auth()->guard(get_auth_guard())->user()->address->country)->first();
    return $exchange_rate;
}
function getUserDefaultCountry()
{
    $exchange_rate = ExchangeRate::where('name', auth()->guard(get_auth_guard())->user()->address->country)->first();
    return $exchange_rate->name;
}
function getUserDefaultCurrencyCode()
{
    $exchange_rate = ExchangeRate::where('name', auth()->guard(get_auth_guard())->user()->address->country)->first();
    return $exchange_rate->currency_code;
}
function getUserDefaultCurrencyRate()
{
    $exchange_rate = ExchangeRate::where('name', auth()->guard(get_auth_guard())->user()->address->country)->first();
    return $exchange_rate->rate;
}

function conversionAmountCalculation($amount, $from_currency_rate, $to_currency_rate){
    return ($amount / $from_currency_rate) * $to_currency_rate;
}


/**
 * Get All Currencies From API
 *
 * @return Illuminate\Http\Request
 */
function getCurrencyList(){

    $credentials  = GatewayAPi::first();
    $response = Http::withToken($credentials->secret_key)->get('https://api.stripe.com/v1/country_specs',[
        'limit' => 41,
    ]);

    $statusCode = $response->getStatusCode();

    if($statusCode == 200){
        $content    = json_decode($response->getBody()->getContents());

        $uniq_currencies = [];

        foreach ($content->data as $key => $item) {
            $uniq_currencies = array_merge($uniq_currencies, array_unique($item->supported_payment_currencies));
        }

        $uniq_currencies = array_values(array_unique($uniq_currencies));

        $currencies = [];

        foreach ($uniq_currencies as  $value) {
            $currency = get_currency_code(strtoupper($value));
            $currencies[] =  (object) $currency;
        }

        return $currencies;
    }else{
        return back()->with(['error' => ['Unable to connect with API, Please Contact Support!']]);
    }

}


function get_gateway_name($gateway_id){
    $gateway = PaymentGateway::where('id',$gateway_id)->first();
    return $gateway->name;
}


function getTotalAmountOnBaseCurr($transactions)
{
    $totalAmount = 0;
    foreach ($transactions as $transaction) {
        $requestAmount = $transaction->request_amount;
        $walletRate = $transaction->creator_wallet->currency->rate;
        $result = $requestAmount / $walletRate;
        $totalAmount += $result;
    }
    return $totalAmount ?? 0 ;
}

function generateTransactionReference()
{
    return 'TXREF_' . time();
}

function selectedLangDir(){
    if(session()->get('local')){
    $default_language = Language::where('code',session()->get('local'))->first();
    $default_language_dir = $default_language->dir ?? LanguageConst::NOT_REMOVABLE;
    }else{
        $default_language = Language::where('status',GlobalConst::ACTIVE)->first();
        $default_language_dir = $default_language->dir ?? LanguageConst::NOT_REMOVABLE;
    }
    return $default_language_dir;
}


//flutterwave automatic withdrawal helper functions
function getFlutterwaveBanks($iso2){

    $cardApi = PaymentGateway::where('type',"AUTOMATIC")->where('alias','flutterwave-money-out')->first();
    $secretKey = getPaymentCredentials($cardApi->credentials,'Secret key');
    $base_url =getPaymentCredentials($cardApi->credentials,'Base Url');
    $curl = curl_init();
    curl_setopt_array($curl, array(
      CURLOPT_URL =>  $base_url.'/banks'.'/'.$iso2,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_HTTPHEADER => array(
        "Authorization: Bearer ". $secretKey
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);
    $banks = json_decode($response,true);
    return $banks['data'];
}
function checkBankAccount($account_number,$bank_code){
    $cardApi = PaymentGateway::where('type',"AUTOMATIC")->where('alias','flutterwave-money-out')->first();
    $secretKey = getPaymentCredentials($cardApi->credentials,'Secret key');
    $base_url =getPaymentCredentials($cardApi->credentials,'Base Url');
    $ch = curl_init();
    $url =   $base_url.'/accounts/resolve';
    $data = [
        "account_number" => $account_number,
        "account_bank" => $bank_code
    ];

    $headers = [
        "Authorization: Bearer ". $secretKey,
        'Content-Type: application/json'
    ];

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        return curl_errno($ch);
    } else {
        $data = json_decode($response,true);
        return $data;
    }

    curl_close($ch);
}


function getPaymentCredentials($credentials,$label){
    $data = null;
    foreach ($credentials as $object) {
        $object = (object)$object;
        if ($object->label === $label) {
            $data = $object;
            break;
        }
    }
    return $data->value;
}



// function flutterwaveBalance($secret_key = null){

//     $cardApi = VirtualCardApi::first();
//     $secretKey = $secret_key??$cardApi->config->flutterwave_secret_key;
//     $base_url = $cardApi->config->flutterwave_url;
//     $curl = curl_init();
//     curl_setopt_array($curl, array(

//         CURLOPT_URL => $base_url.'/balances',
//         CURLOPT_RETURNTRANSFER => true,
//         CURLOPT_ENCODING => "",
//         CURLOPT_MAXREDIRS => 10,
//         CURLOPT_TIMEOUT => 0,
//         CURLOPT_FOLLOWLOCATION => true,
//         CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//         CURLOPT_CUSTOMREQUEST => "GET",
//         CURLOPT_HTTPHEADER => array(

//             "Content-Type: application/json",
//             "Authorization: Bearer ". $secretKey
//         ),
//     ));

//     $response = curl_exec($curl);

//     curl_close($curl);
//     $result = json_decode($response);
//     $currency =  get_default_currency_code();
//     if ($result->status == 'success'){

//         $key = array_search( $currency, array_column($result->data, 'currency'));
//         $balance =  (array) $result;
//         $base_curr =  $balance['data'][$key];
//         $balance = $base_curr->available_balance;
//        $data =[
//         'status' => true,
//         'message' => __("SuccessFully Fetch Account Balance"),
//         'balance' => $balance,
//        ];

//     }else{
//         $data =[
//             'status' => false,
//             'message' => __("Something went wrong! Please try again."),
//             'balance' => 0.0,
//            ];

//     }
//     return $data;
// }
