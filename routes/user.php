<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\User\InvoiceController;
use App\Http\Controllers\User\ProfileController;
use App\Http\Controllers\User\MoneyOutController;
use App\Http\Controllers\User\SecurityController;
use App\Http\Controllers\User\DashboardController;
use App\Http\Controllers\User\WithdrawalController;
use App\Http\Controllers\User\PaymentLinkController;
use App\Http\Controllers\User\TransactionController;
// use App\Http\Controllers\User\SupportTicketController;
use App\Http\Controllers\User\AuthorizationController;
use App\Http\Controllers\User\ProductController;
use App\Http\Controllers\User\ProductLinkController;
use App\Http\Controllers\User\SupportTicketController;

Route::prefix("user")->name("user.")->group(function(){

    Route::controller(DashboardController::class)->group(function(){
        Route::get('dashboard','index')->name('dashboard');
        Route::post('logout','logout')->name('logout');
        Route::delete('delete/account','deleteAccount')->name('delete.account')->middleware('app.mode');
    });

    // Transaction
    Route::controller(TransactionController::class)->prefix('transactions')->name('transactions.')->group(function(){
        Route::get('/{slug?}', 'index')->name('index')->whereIn('slug', ['money-out', 'payment-link', 'invoice']);
        Route::post('search', 'search')->name('search');
    });

    Route::controller(PaymentLinkController::class)->prefix('payment-link')->name('payment-link.')->group(function(){
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store')->middleware('kyc.verification.guard');
        Route::get('/edit/{id}', 'edit')->name('edit');
        Route::post('/update', 'update')->name('update')->middleware('kyc.verification.guard');
        Route::get('/share/{id}', 'share')->name('share');
        Route::delete('delete', 'delete')->name('delete')->middleware('kyc.verification.guard');
        Route::post('/status', 'status')->name('status')->middleware('kyc.verification.guard');
    });

    Route::controller(InvoiceController::class)->prefix('invoice')->name('invoice.')->group(function(){
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store')->middleware('kyc.verification.guard');
        Route::post('/show', 'show')->name('show');
        Route::get('/preview/{id}', 'preview')->name('preview');
        Route::get('/edit/{id}', 'edit')->name('edit');
        Route::post('/update', 'update')->name('update')->middleware('kyc.verification.guard');
        Route::get('/share/{id}', 'share')->name('share');
        Route::delete('delete', 'delete')->name('delete')->middleware('kyc.verification.guard');
        Route::post('/status', 'status')->name('status')->middleware('kyc.verification.guard');
        Route::get('pdf/download{id}', 'downloadPdf')->name('pdf.download');
    });

    Route::controller(ProductController::class)->prefix('product')->name('product.')->group(function(){
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/store', 'store')->name('store')->middleware('kyc.verification.guard');
        Route::get('/edit/{id}', 'edit')->name('edit');
        Route::post('/update', 'update')->name('update')->middleware('kyc.verification.guard');
        Route::put('/status', 'statusUpdate')->name('status.update')->middleware('kyc.verification.guard');
        Route::delete('/delete', 'delete')->name('delete')->middleware('kyc.verification.guard');
    });

    // Product Link
    Route::controller(ProductLinkController::class)->prefix('product-link')->name('product-link.')->group(function(){
        Route::get('/index/{product_id}', 'index')->name('index');
        Route::get('/create/{product_id}', 'create')->name('create');
        Route::post('/store', 'store')->name('store')->middleware('kyc.verification.guard');
        Route::get('/edit/{id}', 'edit')->name('edit');
        Route::post('/update', 'update')->name('update')->middleware('kyc.verification.guard');
        Route::put('/status', 'statusUpdate')->name('status.update')->middleware('kyc.verification.guard');
        Route::get('/share/{id}', 'share')->name('share');
        Route::delete('/delete', 'delete')->name('delete')->middleware('kyc.verification.guard');
    });


    //Withdraw Money
    Route::controller(MoneyOutController::class)->prefix('withdraw')->name('withdraw.')->group(function(){
        Route::get('/','index')->name('index');
        Route::post('insert','paymentInsert')->name('insert')->middleware('kyc.verification.guard');
        Route::get('preview','preview')->name('preview')->middleware('kyc.verification.guard');
        Route::post('confirm','confirmMoneyOut')->name('confirm')->middleware('kyc.verification.guard');
        Route::post('confirm/automatic','confirmMoneyOutAutomatic')->name('confirm.automatic')->middleware('kyc.verification.guard');
    });

    Route::controller(ProfileController::class)->prefix("profile")->name("profile.")->group(function(){
        Route::get('/','index')->name('index');
        Route::put('update','update')->name('update')->middleware('app.mode');
        Route::put('password/update','passwordUpdate')->name('password.update')->middleware('app.mode');
    });

    Route::controller(AuthorizationController::class)->prefix("authorize")->name('authorize.')->group(function(){
        Route::get('kyc','showKycFrom')->name('kyc');
        Route::post('kyc/submit','kycSubmit')->name('kyc.submit');
    });

    Route::controller(SecurityController::class)->prefix("security")->name('security.')->group(function(){
        Route::get('google/2fa','google2FA')->name('google.2fa');
        Route::post('google/2fa/status/update','google2FAStatusUpdate')->name('google.2fa.status.update')->middleware('app.mode');
    });

    Route::controller(SupportTicketController::class)->prefix("support-ticket")->name("support.ticket.")->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('store', 'store')->name('store');
        Route::get('conversation/{encrypt_id}','conversation')->name('conversation');
        Route::post('message/send','messageSend')->name('messaage.send');
    });

});
