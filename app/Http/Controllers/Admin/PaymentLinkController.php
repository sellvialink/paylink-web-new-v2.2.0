<?php

namespace App\Http\Controllers\Admin;

use App\Models\Transaction;
use App\Models\User\PaymentLink;
use App\Http\Controllers\Controller;

class PaymentLinkController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = __('Transactions Logs');
        $transactions = Transaction::with(
            'user:id,firstname,email,username,mobile',
        )->where('type', 'pay-link')->orderBy('id', 'desc')->paginate(20);
        return view('admin.sections.payment-link.index', compact(
            'page_title',
            'transactions'
        ));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function AllLink()
    {
        $page_title = __('All Link');
        $payment_links = PaymentLink::orderBy('id', 'desc')->paginate(12);

        return view('admin.sections.payment-link.payment-link', compact(
            'page_title',
            'payment_links'
        ));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function closedLink()
    {
        $page_title = __('Closed Link');
        $payment_links = PaymentLink::status(2)->orderBy('id', 'desc')->paginate(12);

        return view('admin.sections.payment-link.payment-link', compact(
            'page_title',
            'payment_links'
        ));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function activeLink()
    {
        $page_title = __('Active Link');
        $payment_links = PaymentLink::status(1)->orderBy('id', 'desc')->paginate(12);

        return view('admin.sections.payment-link.payment-link', compact(
            'page_title',
            'payment_links'
        ));
    }

    /**
     * This method for show details of add money
     * @return view $details-payment-link-logs
     */
    public function details($id){
        $data = Transaction::where('id',$id)->with(
            'user:id,firstname,email,username,full_mobile',
            'currency:id,name,alias,payment_gateway_id,currency_code,rate',
        )->where('type', 'pay-link')->first();

        $page_title = "Payment Link details";
        return view('admin.sections.payment-link.details', compact(
            'page_title',
            'data'
        ));
    }
}
