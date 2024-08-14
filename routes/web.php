<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\DonationController;
use App\Http\Controllers\User\InvoiceController;
use App\Http\Controllers\User\PaymentLinkController;
use App\Http\Controllers\User\ProductLinkController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

//landing page
Route::controller(SiteController::class)->group(function(){
    Route::get('/','home')->name('index');
    Route::get('services','services')->name('services');
    Route::get('about-us','aboutUs')->name('about-us');
    Route::get('web-journal','webJournal')->name('web-journal');
    Route::get('web-journal/details/{id}/{slug}','webJournalDetails')->name('web-journal.details');
    Route::get('contact-us','contactUs')->name('contact.us');
    Route::get('page/{slug}','pageView')->name('page.view');
    Route::post('subscriber','subscriber')->name('subscriber');
    Route::post('contact/store','contactStore')->name('contact.store');
    Route::post('languages/switch','languageSwitch')->name('languages.switch');


    Route::get('redirect/btn/checkout/{gateway}', 'redirectBtnPay')->name('payment.btn.pay');
    Route::post("callback/invoice/response/{gateway}",'callback')->name('payment.callback')->withoutMiddleware(['web']);

});

// Payment Link
Route::controller(PaymentLinkController::class)->prefix('/payment-link')->name('payment-link.')->group(function(){
    Route::get('/share/{token}','paymentLinkShare')->name('share');
    Route::post('/submit','paymentLinkSubmit')->name('submit');
    Route::get('/transaction/success/{token}','transactionSuccess')->name('transaction.success');

    Route::prefix('payment')->name('payment.')->group(function(){
        // Stripe
        Route::get('success/stripe/{trx}', 'stripeSuccess')->name('success.stripe');

        // Paypal
        Route::get('success/paypal/{gateway}', 'paypalSuccess')->name('success.paypal');
        Route::get('cancel/paypal/{gateway}', 'paypalCancel')->name('cancel.paypal');

        // Flutterwave
        Route::get('callback/flutterwave/{token}', 'flutterwaveCallback')->name('callback.flutterwave');

        // SSL Commerze
        Route::post('success/sslcommerz','sslCommerzSuccess')->name('success.ssl');
        Route::post('fail/sslcommerz','sslCommerzFails')->name('fail.ssl');
        Route::post('cancel/sslcommerz','sslCommerzCancel')->name('cancel.ssl');

        // Qrpay gateway
        Route::get('qrpay/callback', 'qrpayCallback')->name('qrpay.callback');
        Route::get('qrpay/cancel/{trx_id}', 'qrpayCancel')->name('qrpay.cancel');

    // Pagadito
        Route::get('pagadito/success','pagaditoSuccess')->name('success');

        //coingate
        Route::match(['get','post'],'coingate/success/response/{gateway}','coinGateSuccess')->name('coingate.payment.success');
        Route::match(['get','post'],"coingate/cancel/response/{gateway}",'coinGateCancel')->name('coingate.payment.cancel');
        Route::post("coingate/callback/response/{gateway}",'coingateCallback')->name('coingate.payment.callback')->withoutMiddleware('web');

        // Perfect Money
        Route::get('redirect/form/{gateway}', 'redirectUsingHTMLForm')->name('payment.redirect.form');

        Route::get('perfect.success/response/{gateway}','perfectSuccess')->name('perfect.payment.success');
        Route::get("perfect.cancel/response/{gateway}",'perfectCancel')->name('perfect.payment.cancel');
        Route::post("perfect.callback/response/{gateway}",'perfectCallback')->name('perfect.payment.callback')->withoutMiddleware(['web']);

        Route::get('success/response/{gateway}','successGlobal')->name('payment.global.success');
        Route::get("cancel/response/{gateway}",'cancelGlobal')->name('payment.global.cancel');

        Route::post('success/response/{gateway}', 'postSuccess')->name('payment.global.success');
        Route::post('cancel/response/{gateway}', 'postCancel')->name('payment.global.cancel');
    });
});

// Invoice
Route::controller(InvoiceController::class)->prefix('/invoice')->name('invoice.')->group(function(){
    Route::get('/share/{token}','invoiceShare')->name('share');
    Route::post('/submit','invoiceSubmit')->name('submit');
    Route::get('/download/{id}', 'downloadPdf')->name('download');
    Route::get('/transaction/success/{token}','transactionSuccess')->name('transaction.success');

    Route::prefix('payment')->name('payment.')->group(function(){
        // Stripe
        Route::get('success/stripe/{trx}', 'stripeSuccess')->name('success.stripe');

        // Paypal
        Route::get('success/paypal/{gateway}', 'paypalSuccess')->name('success.paypal');
        Route::get('cancel/paypal/{gateway}', 'paypalCancel')->name('cancel.paypal');

        // Flutterwave
        Route::get('callback/flutterwave/{token}', 'flutterwaveCallback')->name('callback.flutterwave');

        // SSL Commerze
        Route::post('success/sslcommerz','sslCommerzSuccess')->name('success.ssl');
        Route::post('fail/sslcommerz','sslCommerzFails')->name('fail.ssl');
        Route::post('cancel/sslcommerz','sslCommerzCancel')->name('cancel.ssl');

        // Qrpay gateway
        Route::get('qrpay/callback', 'qrpayCallback')->name('qrpay.callback');
        Route::get('qrpay/cancel/{trx_id}', 'qrpayCancel')->name('qrpay.cancel');

        //coingate
        Route::match(['get','post'],'coingate/success/response/{gateway}','coinGateSuccess')->name('coingate.payment.success');
        Route::match(['get','post'],"coingate/cancel/response/{gateway}",'coinGateCancel')->name('coingate.payment.cancel');
        Route::post("coingate/callback/response/{gateway}",'coingateCallback')->name('coingate.payment.callback')->withoutMiddleware('web');


        Route::get('redirect/form/{gateway}', 'redirectUsingHTMLForm')->name('payment.redirect.form');

        Route::get('perfect.success/response/{gateway}','perfectSuccess')->name('perfect.payment.success');
        Route::get("perfect.cancel/response/{gateway}",'perfectCancel')->name('perfect.payment.cancel');
        Route::post("perfect.callback/response/{gateway}",'perfectCallback')->name('perfect.payment.callback')->withoutMiddleware(['web']);

        Route::get('redirect/btn/checkout/{gateway}', 'redirectBtnPay')->name('payment.btn.pay');

        Route::get('success/response/{gateway}','successGlobal')->name('payment.global.success');
        Route::get("cancel/response/{gateway}",'cancelGlobal')->name('payment.global.cancel');

        Route::post('success/response/{gateway}', 'postSuccess')->name('payment.global.success');
        Route::post('cancel/response/{gateway}', 'postCancel')->name('payment.global.cancel');
    });
});

// Products
Route::controller(ProductLinkController::class)->prefix('/product-link')->name('product-link.')->group(function(){
    Route::get('/share/{token}','productLinkShare')->name('share');
    Route::post('/submit','productLinkSubmit')->name('submit');
    Route::get('/transaction/success/{token}','transactionSuccess')->name('transaction.success');

    Route::prefix('payment')->name('payment.')->group(function(){
        // Stripe
        Route::get('success/stripe/{trx}', 'stripeSuccess')->name('success.stripe');

        // Paypal
        Route::get('success/paypal/{gateway}', 'paypalSuccess')->name('success.paypal');
        Route::get('cancel/paypal/{gateway}', 'paypalCancel')->name('cancel.paypal');

        // Flutterwave
        Route::get('callback/flutterwave/{token}', 'flutterwaveCallback')->name('callback.flutterwave');

        // SSL Commerze
        Route::post('success/sslcommerz','sslCommerzSuccess')->name('success.ssl');
        Route::post('fail/sslcommerz','sslCommerzFails')->name('fail.ssl');
        Route::post('cancel/sslcommerz','sslCommerzCancel')->name('cancel.ssl');

        // Qrpay gateway
        Route::get('qrpay/callback', 'qrpayCallback')->name('qrpay.callback');
        Route::get('qrpay/cancel/{trx_id}', 'qrpayCancel')->name('qrpay.cancel');

        //coingate
        Route::match(['get','post'],'coingate/success/response/{gateway}','coinGateSuccess')->name('coingate.payment.success');
        Route::match(['get','post'],"coingate/cancel/response/{gateway}",'coinGateCancel')->name('coingate.payment.cancel');
        Route::post("coingate/callback/response/{gateway}",'coingateCallback')->name('coingate.payment.callback')->withoutMiddleware('web');


        Route::get('redirect/form/{gateway}', 'redirectUsingHTMLForm')->name('payment.redirect.form');

        Route::get('perfect.success/response/{gateway}','perfectSuccess')->name('perfect.payment.success');
        Route::get("perfect.cancel/response/{gateway}",'perfectCancel')->name('perfect.payment.cancel');
        Route::post("perfect.callback/response/{gateway}",'perfectCallback')->name('perfect.payment.callback')->withoutMiddleware(['web']);

        Route::get('redirect/btn/checkout/{gateway}', 'redirectBtnPay')->name('payment.btn.pay');

        Route::get('success/response/{gateway}','successGlobal')->name('payment.global.success');
        Route::get("cancel/response/{gateway}",'cancelGlobal')->name('payment.global.cancel');

        Route::post('success/response/{gateway}', 'postSuccess')->name('payment.global.success');
        Route::post('cancel/response/{gateway}', 'postCancel')->name('payment.global.cancel');
    });
});



