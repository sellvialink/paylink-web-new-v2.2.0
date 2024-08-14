<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\GlobalController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\MoneyOutController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\AppSettingsController;
use App\Http\Controllers\Api\V1\PaymentLinkController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\Auth\AuthorizationController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductLinkController;

Route::name('api.v1.')->group(function(){
    Route::get('basic/settings', [AppSettingsController::class, "basicSettings"]);

    // User
    Route::group(['prefix' => 'user', 'as' => 'user.'], function(){
        Route::post('login', [AuthController::class, 'login']);
        Route::post('register', [AuthController::class, 'register']);

        Route::group(['prefix' => 'forgot/password'], function () {
            Route::post('send/otp', [ForgotPasswordController::class, 'sendCode']);
            Route::post('verify',  [ForgotPasswordController::class, 'verifyCode']);
            Route::post('reset', [ForgotPasswordController::class, 'resetPassword']);
        });

        Route::middleware('auth:api')->group(function (){
            Route::get('dashboard', [DashboardController::class, 'dashboard']);
            Route::get('logout', [AuthorizationController::class, 'logout']);
            Route::post('email/otp/verify', [AuthorizationController::class,'verifyEmailCode']);
            Route::post('email/resend/code', [AuthorizationController::class,'emailResendCode']);
            Route::post('google-2fa/otp/verify', [AuthorizationController::class,'verify2FACode']);

            Route::middleware('checkStatusApiUser')->group(function(){

                // Currency list
                Route::get('notification/list', [GlobalController::class, 'notificationList']);
                // User Profile
                Route::controller(ProfileController::class)->prefix('profile')->group(function(){
                    Route::get('/', 'profile');
                    Route::post('update', 'profileUpdate')->middleware('app.mode');
                    Route::post('password/update', 'passwordUpdate')->middleware('app.mode');
                    Route::post('delete/account', 'deleteAccount')->middleware('app.mode');
                    Route::get('/google-2fa', 'google2FA');
                    Route::post('/google-2fa/status/update', 'google2FAStatusUpdate')->middleware('app.mode');

                    Route::controller(AuthorizationController::class)->prefix('kyc')->group(function(){
                        Route::get('input-fields','getKycInputFields');
                        Route::post('submit','KycSubmit');
                    });
                });

                 // Payment Link
                 Route::controller(PaymentLinkController::class)->prefix('payment-links/')->group(function(){
                    Route::get('/', 'index');
                    Route::post('/store', 'store');
                    Route::get('/edit', 'edit');
                    Route::post('/update', 'update');
                    Route::post('/status', 'status');
                });

                // Product Link
                Route::controller(ProductController::class)->prefix('products/')->group(function(){
                    Route::get('/', 'index');
                    Route::post('/store', 'store');
                    Route::post('/update', 'update');
                    Route::get('/edit', 'edit');
                    Route::post('/update', 'update');
                    Route::post('/status', 'status');
                    Route::post('/delete', 'delete');
                });

                // Product Link
                Route::controller(ProductLinkController::class)->prefix('product-links/')->group(function(){
                    Route::get('/', 'index');
                    Route::post('/store', 'store');
                    Route::post('/update', 'update');
                    Route::get('/edit', 'edit');
                    Route::post('/update', 'update');
                    Route::post('/status', 'status');
                    Route::post('/delete', 'delete');
                });

                 // Invoice
                 Route::controller(InvoiceController::class)->prefix('invoice/')->group(function(){
                    Route::get('/', 'index');
                    Route::post('/store', 'store');
                    Route::post('/update', 'update');
                    Route::post('/status', 'status');
                    Route::get('/edit', 'edit');
                    Route::get('/download/invoice', 'downloadInvoice')->name('download.invoice');
                    Route::post('/delete', 'delete');
                });

                //Withdraw Money
                Route::controller(MoneyOutController::class)->prefix('withdraw')->group(function(){
                    Route::get('info','moneyOutInfo');
                    Route::post('insert','moneyOutInsert');
                    Route::post('manual/confirmed','moneyOutConfirmed')->name('withdraw.manual.confirmed');
                    Route::post('automatic/confirmed','confirmMoneyOutAutomatic')->name('withdraw.automatic.confirmed');
                    //get flutterWave banks
                    Route::get('get/flutterwave/banks','getBanks');
                });

                //transactions
                Route::controller(TransactionController::class)->prefix("transactions")->group(function(){
                    Route::get('/{slug?}','index');
                });

            });
        });

    });
});
