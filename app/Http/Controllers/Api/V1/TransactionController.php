<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Transaction;
use App\Http\Controllers\Controller;
use App\Constants\PaymentGatewayConst;
use App\Http\Helpers\Api\Helpers as apiResponse;
use App\Http\Resources\User\MoneyOutResource;
use App\Http\Resources\User\PayInvoiceResource;
use App\Http\Resources\User\PayLinkResource;

class TransactionController extends Controller
{
    public function index($slug = null) {

        // start transaction now
        $moneyOut           = Transaction::auth()->moneyOut()->orderByDesc("id")->get();
        $pay_invoice        = Transaction::auth()->payInvoice()->orderByDesc('id')->get();
        $pay_link           = Transaction::auth()->payLink()->orderByDesc('id')->get();
        $add_sub_balance    = Transaction::auth()->addSubBalance()->orderByDesc('id')->get();

        $transactions = [
            'money_out'   => MoneyOutResource::collection($moneyOut),
            'pay_invoice' => PayInvoiceResource::collection($pay_invoice),
            'pay_link'    => PayLinkResource::collection($pay_link),
        ];

        $transactions = (object)$transactions;

        $transaction_types = [
            'money_out'                 => PaymentGatewayConst::TYPEMONEYOUT,
            'pay_invoice'               => PaymentGatewayConst::TYPEINVOICE,
            'pay_link'                  => PaymentGatewayConst::TYPEPAYLINK,
        ];

        $transaction_types = (object)$transaction_types;

        $data =[
            'transaction_types' => $transaction_types,
            'transactions'=> $transactions,
        ];


        $message =  ['success'=>[__('All Transactions Fetch Successful')]];
        return apiResponse::success($message, $data);
    }
}
