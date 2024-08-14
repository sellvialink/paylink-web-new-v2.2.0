<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\UserWallet;
use App\Models\Transaction;
use App\Models\User\Invoice;
use App\Models\User\PaymentLink;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Http\Helpers\Api\Helpers as ApiResponse;
use App\Http\Resources\User\TransactionResource;

class DashboardController extends Controller
{
    /**
     * Dashboard Data Fetch
     *
     * @method GET
     * @return \Illuminate\Http\Response
    */

    public function dashboard(){

        $wallet = UserWallet::with('currency')->where('user_id', Auth::id())->get()->map(function ($item){
            return [
                'id'      => $item->id,
                'code'    => $item->currency->currency_code,
                'name'    => $item->currency->name,
                'balance' => get_amount($item->balance),
            ];
        })->first();

        $collection_payment = Transaction::auth()->payLink()->sum('conversion_payable');
        $collection_invoice = Transaction::auth()->payInvoice()->sum('conversion_payable');
        $money_out          = Transaction::auth()->moneyOut()->where('status', 1)->sum('request_amount');
        $total_payment_link = PaymentLink::auth()->count();
        $total_invoice      = Invoice::auth()->count();

        $get_transactions        = Transaction::auth()->orderByDesc('id')->get();
        $transactions            = TransactionResource::collection($get_transactions);
        // dd($transactions);

        $data = [
            'wallet'             => $wallet,
            'collection_payment' => get_amount($collection_payment),
            'collection_invoice' => get_amount($collection_invoice),
            'money_out'          => get_amount($money_out),
            'total_payment_link' => get_amount($total_payment_link),
            'total_invoice'      => get_amount($total_invoice),
            'transactions'       => $transactions,
        ];

        $message =  ['success'=>[__('Dashboard data successfully fetch!')]];
        return ApiResponse::success($message, $data);
    }


}
