<?php

namespace App\Http\Controllers\Admin;

use App\Models\Transaction;
use App\Models\User\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Http\Controllers\Controller;
use App\Constants\PaymentGatewayConst;

class InvoiceController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = __('Transaction Logs');
        $transactions = Transaction::with(
            'user:id,firstname,email,username,mobile',
        )->where('type', PaymentGatewayConst::TYPEINVOICE)->orderBy('id', 'desc')->paginate(20);
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
    public function AllInvoice()
    {
        $page_title = __('All Invoice');
        $invoices = Invoice::orderBy('id', 'desc')->paginate(12);

        return view('admin.sections.payment-link.invoice', compact(
            'page_title',
            'invoices'
        ));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function paidInvoice()
    {
        $page_title = __('Paid Invoice');
        $invoices = Invoice::status(1)->orderBy('id', 'desc')->paginate(12);

        return view('admin.sections.payment-link.invoice', compact(
            'page_title',
            'invoices'
        ));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function unpaidInvoice()
    {
        $page_title = __('Unpaid Invoice');
        $invoices = Invoice::status(2)->orderBy('id', 'desc')->paginate(12);

        return view('admin.sections.payment-link.invoice', compact(
            'page_title',
            'invoices'
        ));
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function draftInvoice()
    {
        $page_title = __('Draft Invoice');
        $invoices = Invoice::status(3)->orderBy('id', 'desc')->paginate(12);

        return view('admin.sections.payment-link.invoice', compact(
            'page_title',
            'invoices'
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
        )->where('type', PaymentGatewayConst::TYPEINVOICE)->first();

        $page_title = __("Invoice details");
        return view('admin.sections.payment-link.details', compact(
            'page_title',
            'data'
        ));
    }


    /**
     * PDF Download
     *
     * @param @return Illuminate\Http\Request $request
     * @method GET
     * @return Illuminate\Http\Request
     */
    public function download($id){

        $invoice = Invoice::with('invoiceItems')->findOrFail($id);
        $data = [
            'invoice' => $invoice,
        ];
        $pdf = Pdf::loadView('user.sections.invoice.pdf-generate', $data)->setOption(['dpi' => 150, 'defaultFont' => 'sans-serif']);
        $pdf_download_name =  $invoice->invoice_no ?? now()->format("d-m-Y H:i");
        return $pdf->download($pdf_download_name.".pdf");
    }
}
