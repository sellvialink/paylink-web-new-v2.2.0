<?php

namespace App\Http\Controllers\Admin;

use App\Constants\PaymentGatewayConst;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\ProductLink;

class ProductLinkController extends Controller
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
        )->where('type', PaymentGatewayConst::TYPEPRODUCT)->orderBy('id', 'desc')->paginate(20);
        return view('admin.sections.product-link.index', compact(
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
        $payment_links = ProductLink::with('product')->orderBy('id', 'desc')->paginate(12);
        return view('admin.sections.product-link.product-link', compact(
            'page_title',
            'payment_links'
        ));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function inactiveLink()
    {
        $page_title = __('Inactive Link');
        $payment_links = ProductLink::with('product')->status(2)->orderBy('id', 'desc')->paginate(12);

        return view('admin.sections.product-link.product-link', compact(
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
        $payment_links = ProductLink::with('product')->status(1)->orderBy('id', 'desc')->paginate(12);

        return view('admin.sections.product-link.product-link', compact(
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
        )->where('type', PaymentGatewayConst::TYPEPRODUCT)->first();

        $page_title = "Product Link details";
        return view('admin.sections.payment-link.details', compact(
            'page_title',
            'data'
        ));
    }
}
