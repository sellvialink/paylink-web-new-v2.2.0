<?php

namespace App\Http\Controllers\Admin;

use Exception;
use App\Models\User;
use App\Models\Subscriber;
use App\Models\Transaction;
use App\Models\User\Invoice;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Models\User\PaymentLink;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Constants\PaymentGatewayConst;
use App\Models\ProductLink;
use App\Providers\Admin\BasicSettingsProvider;
use Pusher\PushNotifications\PushNotifications;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $page_title = "Dashboard";

        $last_month_start = date('Y-m-01', strtotime('-1 month', strtotime(date('Y-m-d'))));
        $last_month_end   = date('Y-m-31', strtotime('-1 month', strtotime(date('Y-m-d'))));
        $this_month_start = date('Y-m-01');
        $today            = date('Y-m-d');
        $this_month_end   = date('Y-m-d');
        $this_weak        = date('Y-m-d', strtotime('-1 week', strtotime(date('Y-m-d'))));
        $this_month       = date('Y-m-d', strtotime('-1 month', strtotime(date('Y-m-d'))));
        $this_year        = date('Y-m-d', strtotime('-1 year', strtotime(date('Y-m-d'))));

        //************* Dashboard Box Data Start *******************//

        // Money Out
        $money_out_balance       = Transaction::toBase()->where('type', PaymentGatewayConst::TYPEMONEYOUT)->where('status', 1)->sum('request_amount_admin');
        $money_out_total_balance = Transaction::toBase()->where('type', PaymentGatewayConst::TYPEMONEYOUT)->sum('request_amount_admin');
        $today_money_out =  Transaction::toBase()
                            ->where('type', PaymentGatewayConst::TYPEMONEYOUT)
                            ->where('status', 1)
                            ->whereDate('created_at','>=',$this_month_start)
                            ->whereDate('created_at','<=',$this_month_end)
                            ->sum('request_amount_admin');
        $last_month_money_out =  Transaction::toBase()->where('status', 1)
                                            ->where('type', PaymentGatewayConst::TYPEMONEYOUT)
                                            ->whereDate('created_at','>=',$last_month_start)
                                            ->whereDate('created_at','<=',$last_month_end)
                                            ->sum('request_amount_admin');

        $last_month_money_out_p = $last_month_money_out == 0 ? 1 : $last_month_money_out;
        $money_out_percent = (($today_money_out * 100) / $last_month_money_out_p);

        if($money_out_percent > 100){
            $money_out_percent = 100;
        }

        // Profit
        $profit_balance = DB::table('transactions')
                                        ->where('transactions.status', 1)
                                        ->join('transaction_charges', 'transactions.id', 'transaction_charges.transaction_id')
                                        ->select('total_charge')
                                        ->sum('total_charge');

        $last_month_profit = DB::table('transactions')
                                        ->where('transactions.status', 1)
                                        ->whereDate('transactions.created_at','>=',$last_month_start)
                                        ->whereDate('transactions.created_at','<=',$last_month_end)
                                        ->join('transaction_charges', 'transactions.id', 'transaction_charges.transaction_id')
                                        ->select('total_charge')
                                        ->sum('total_charge');

        $today_profit = DB::table('transactions')
                        ->where('transactions.status', 1)
                        ->whereDate('transactions.created_at','=',$today)
                        ->join('transaction_charges', 'transactions.id', 'transaction_charges.transaction_id')
                        ->select('total_charge')
                        ->sum('total_charge');

        $this_week_profit = DB::table('transactions')
                        ->where('transactions.status', 1)
                        ->whereDate('transactions.created_at','>=',$this_weak)
                        ->join('transaction_charges', 'transactions.id', 'transaction_charges.transaction_id')
                        ->select('total_charge')
                        ->sum('total_charge');

        $this_month_profit = DB::table('transactions')
                        ->where('transactions.status', 1)
                        ->whereDate('transactions.created_at','>=',$this_month)
                        ->join('transaction_charges', 'transactions.id', 'transaction_charges.transaction_id')
                        ->select('total_charge')
                        ->sum('total_charge');

        $this_year_profit = DB::table('transactions')
                        ->where('transactions.status', 1)
                        ->whereDate('transactions.created_at','>=',$this_year)
                        ->join('transaction_charges', 'transactions.id', 'transaction_charges.transaction_id')
                        ->select('total_charge')
                        ->sum('total_charge');


        $last_month_profit_p = $last_month_profit == 0 ? 1 : $last_month_profit;
        $profit_percent = (($this_month_profit * 100) / $last_month_profit_p);

        if($profit_percent > 100){
            $profit_percent = 100;
        }


        // Collect Payment With Invoice

        $transaction_invoice_balance = Transaction::toBase()->where('status', PaymentGatewayConst::ACTIVE)->where('type', PaymentGatewayConst::TYPEINVOICE)->sum('request_amount_admin');
        $today_transaction_invoice =  Transaction::toBase()
                            ->where('status', PaymentGatewayConst::ACTIVE)
                            ->where('type', PaymentGatewayConst::TYPEINVOICE)
                            ->whereDate('created_at','>=',$this_month_start)
                            ->whereDate('created_at','<=',$this_month_end)
                            ->sum('request_amount_admin');
        $last_month_transaction_invoice =  Transaction::toBase()
                                            ->where('status', PaymentGatewayConst::ACTIVE)
                                            ->where('type', PaymentGatewayConst::TYPEINVOICE)
                                            ->whereDate('created_at','>=',$last_month_start)
                                            ->whereDate('created_at','<=',$last_month_end)
                                            ->sum('request_amount_admin');

        if($last_month_transaction_invoice == 0){
            $transaction_invoice_percent = 100;
        }else{
            $transaction_invoice_percent = (($transaction_invoice_balance * 100) / $last_month_transaction_invoice);
        }

        // Collect Payment With Payment Link

        $transaction_payment_link_balance = Transaction::toBase()->where('status', PaymentGatewayConst::ACTIVE)->where('type', PaymentGatewayConst::TYPEPAYLINK)->sum('request_amount_admin');

        $today_transaction_payment_link =  Transaction::toBase()
                            ->where('status', PaymentGatewayConst::ACTIVE)
                            ->where('type', PaymentGatewayConst::TYPEPAYLINK)
                            ->whereDate('created_at','>=',$this_month_start)
                            ->whereDate('created_at','<=',$this_month_end)
                            ->sum('request_amount_admin');
        $last_month_transaction_payment_link =  Transaction::toBase()
                                            ->where('status', PaymentGatewayConst::ACTIVE)
                                            ->where('type', PaymentGatewayConst::TYPEPAYLINK)
                                            ->whereDate('created_at','>=',$last_month_start)
                                            ->whereDate('created_at','<=',$last_month_end)
                                            ->sum('request_amount_admin');

        if($last_month_transaction_payment_link == 0){
            $transaction_payment_link_percent = 100;
        }else{
            $transaction_payment_link_percent = (($transaction_payment_link_balance * 100) / $last_month_transaction_payment_link);
        }

        // Collect Payment With Product

        $transaction_product_balance = Transaction::toBase()->where('status', PaymentGatewayConst::ACTIVE)->where('type', PaymentGatewayConst::TYPEPRODUCT)->sum('request_amount_admin');

        $today_transaction_product =  Transaction::toBase()
                            ->where('status', PaymentGatewayConst::ACTIVE)
                            ->where('type', PaymentGatewayConst::TYPEPRODUCT)
                            ->whereDate('created_at','>=',$this_month_start)
                            ->whereDate('created_at','<=',$this_month_end)
                            ->sum('request_amount_admin');

        $last_month_transaction_product =  Transaction::toBase()
                                            ->where('status', PaymentGatewayConst::ACTIVE)
                                            ->where('type', PaymentGatewayConst::TYPEPRODUCT)
                                            ->whereDate('created_at','>=',$last_month_start)
                                            ->whereDate('created_at','<=',$last_month_end)
                                            ->sum('request_amount_admin');

        if($last_month_transaction_product == 0){
            $transaction_product_percent = 100;
        }else{
            $transaction_product_percent = (($transaction_product_balance * 100) / $last_month_transaction_product);
        }

        // Collect Payment With Product Link

        $transaction_product_link_balance = Transaction::toBase()->where('status', PaymentGatewayConst::ACTIVE)->where('type', PaymentGatewayConst::TYPEPRODUCT)->sum('request_amount_admin');

        $today_transaction_payment_link =  Transaction::toBase()
                            ->where('status', PaymentGatewayConst::ACTIVE)
                            ->where('type', PaymentGatewayConst::TYPEPAYLINK)
                            ->whereDate('created_at','>=',$this_month_start)
                            ->whereDate('created_at','<=',$this_month_end)
                            ->sum('request_amount_admin');
        $last_month_transaction_payment_link =  Transaction::toBase()
                                            ->where('status', PaymentGatewayConst::ACTIVE)
                                            ->where('type', PaymentGatewayConst::TYPEPAYLINK)
                                            ->whereDate('created_at','>=',$last_month_start)
                                            ->whereDate('created_at','<=',$last_month_end)
                                            ->sum('request_amount_admin');

        if($last_month_transaction_payment_link == 0){
            $transaction_payment_link_percent = 100;
        }else{
            $transaction_payment_link_percent = (($transaction_product_link_balance * 100) / $last_month_transaction_payment_link);
        }

        // Payment Link
        $total_payment_link   = PaymentLink::toBase()->count();
        $active_payment_link  = PaymentLink::toBase()->where('status', 1)->count();
        $closed_payment_link  = PaymentLink::toBase()->where('status', 2)->count();
        $total_payment_link_p = $total_payment_link == 0 ? 1 : $total_payment_link;
        $payment_link_percent = (($active_payment_link * 100) / $total_payment_link_p);

        if($payment_link_percent > 100){
            $payment_link_percent = 100;
        }

        // Product Link
        $total_product_link   = ProductLink::toBase()->count();
        $active_product_link  = ProductLink::toBase()->where('status', 1)->count();
        $inactive_product_link  = ProductLink::toBase()->where('status', 2)->count();
        $total_product_link_p = $total_product_link == 0 ? 1 : $total_product_link;
        $product_link_percent = (($active_product_link * 100) / $total_product_link_p);

        if($product_link_percent > 100){
            $product_link_percent = 100;
        }

        // Invoice
        $total_invoice   = Invoice::toBase()->count();
        $paid_invoice    = Invoice::toBase()->where('status', 1)->count();
        $unpaid_invoice  = Invoice::toBase()->where('status', 2)->count();
        $draft_invoice   = Invoice::toBase()->where('status', 3)->count();
        $total_invoice_p = $total_invoice == 0 ? 1 : $total_invoice;
        $invoice_percent = (($paid_invoice * 100) / $total_invoice_p);

        if($invoice_percent > 100){
            $invoice_percent = 100;
        }

        // User
        $total_user      = User::toBase()->count();
        $unverified_user = User::toBase()->where('email_verified', 0)->count();
        $active_user     = User::toBase()->where('status', 1)->count();
        $banned_user     = User::toBase()->where('status', 0)->count();
        $total_user_p    = $total_user == 0 ? 1 : $total_user;
        $user_percent    = (($active_user * 100) / $total_user_p);

        if($user_percent > 100){
            $user_percent = 100;
        }

        // Subscriber
        $total_subscriber = Subscriber::toBase()->count();
        $today_subscriber = Subscriber::toBase()
                            ->whereDate('created_at','>=',$this_month_start)
                            ->whereDate('created_at','<=',$this_month_end)
                            ->count();
        $last_month_subscriber = Subscriber::toBase()
                                        ->whereDate('created_at','>=',$last_month_start)
                                        ->whereDate('created_at','<=',$last_month_end)
                                        ->count();
        $last_month_subscriber_p = $last_month_subscriber == 0 ? 1 : $last_month_subscriber;
        $subscriber_percent =(($today_subscriber * 100) / $last_month_subscriber_p);

        if($subscriber_percent > 100){
            $subscriber_percent = 100;
        }

        //************* Dashboard Box Data End *******************//


        // Monthly Add Money
        $start = strtotime(date('Y-m-01'));
        $end = strtotime(date('Y-m-31'));

        $month_day  = [];
        $chart_money_out_balance = [];
        $chart_invoice_balance = [];
        $chart_payment_link_balance = [];
        $chart_product_link_balance = [];

        while ($start <= $end) {
            $start_date = date('Y-m-d', $start);

            $money_out = Transaction::toBase()->where('type', PaymentGatewayConst::TYPEMONEYOUT)
                                ->where('status', 1)
                                ->whereDate('created_at',$start_date)
                                ->sum('request_amount_admin');

            $invoice = Transaction::toBase()
                                    ->where('status', 1)
                                    ->where('type', PaymentGatewayConst::TYPEINVOICE)
                                    ->whereDate('created_at',$start_date)
                                    ->sum('request_amount_admin');

            $payment_link = Transaction::toBase()
                                    ->where('status', 1)
                                    ->where('type', PaymentGatewayConst::TYPEPAYLINK)
                                    ->whereDate('created_at',$start_date)
                                    ->sum('request_amount_admin');

            $product_link = Transaction::toBase()
                                    ->where('status', 1)
                                    ->where('type', PaymentGatewayConst::TYPEPRODUCT)
                                    ->whereDate('created_at',$start_date)
                                    ->sum('request_amount_admin');

            // Chart For Analytics (Chart One Data)
            $chart_money_out_balance[]    = get_amount($money_out);
            $chart_invoice_balance[]      = get_amount($invoice);
            $chart_payment_link_balance[] = get_amount($payment_link);
            $chart_product_link_balance[] = get_amount($product_link);

            $month_day[] = date('Y-m-d', $start);
            $start = strtotime('+1 day',$start);
        }

        $chart_three_data = [
            'month_day'                  => $month_day,
            'chart_money_out_balance'    => $chart_money_out_balance,
            'chart_invoice_balance'      => $chart_invoice_balance,
            'chart_payment_link_balance' => $chart_payment_link_balance,
            'chart_product_link_balance' => $chart_product_link_balance,
        ];

        $chart_four_data = [$active_user, $banned_user,$unverified_user,$total_user];
        $chart_five = [round($today_profit), round($this_week_profit),round($this_month_profit),round($this_year_profit)];


        $transactions = Transaction::with(
            'user:id,firstname,email,username,mobile',
        )->whereNot('type', PaymentGatewayConst::TYPEADDSUBTRACTBALANCE)->orderBy('id', 'desc')->limit(3)->get();


        return view('admin.sections.dashboard.index',compact(
            'page_title',

            'total_user',
            'unverified_user',
            'active_user',
            'user_percent',

            'transaction_invoice_balance',
            'today_transaction_invoice',
            'last_month_transaction_invoice',
            'transaction_invoice_percent',

            'transaction_payment_link_balance',
            'today_transaction_payment_link',
            'last_month_transaction_payment_link',
            'transaction_payment_link_percent',

            'transaction_product_balance',
            'today_transaction_product',
            'last_month_transaction_product',
            'transaction_product_percent',

            'money_out_balance',
            'money_out_total_balance',
            'today_money_out',
            'last_month_money_out',
            'money_out_percent',

            'total_payment_link',
            'active_payment_link',
            'closed_payment_link',
            'payment_link_percent',

            'total_product_link',
            'active_product_link',
            'inactive_product_link',
            'product_link_percent',

            'total_invoice',
            'paid_invoice',
            'unpaid_invoice',
            'draft_invoice',
            'invoice_percent',


            'profit_balance',
            'today_profit',
            'last_month_profit',
            'profit_percent',

            'total_subscriber',
            'today_subscriber',
            'last_month_subscriber',
            'subscriber_percent',

            'transactions',
            'chart_three_data',
            'chart_four_data',
            'chart_five'
        ));

    }


    /**
     * Logout Admin From Dashboard
     * @return view
     */
    public function logout(Request $request) {

        $push_notification_setting = BasicSettingsProvider::get()->push_notification_config;

        if($push_notification_setting) {
            $method = $push_notification_setting->method ?? false;

            if($method == "pusher") {
                $instant_id     = $push_notification_setting->instance_id ?? false;
                $primary_key    = $push_notification_setting->primary_key ?? false;

                if($instant_id && $primary_key) {

                    try {
                        $pusher_instance = new PushNotifications([
                            "instanceId"    => $instant_id,
                            "secretKey"     => $primary_key,
                        ]);

                        $pusher_instance->deleteUser("".Auth::user()->id."");
                    } catch (\Exception $th) {
                        //Handle Error;
                    }

                }
            }

        }

        $admin = auth()->user();
        try{
            $admin->update([
                'last_logged_out'   => now(),
                'login_status'      => false,
            ]);
        }catch(Exception $e) {
            // Handle Error
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }


    /**
     * Function for clear admin notification
     */
    public function notificationsClear() {
        $admin = auth()->user();

        if(!$admin) {
            return false;
        }

        try{
            $admin->update([
                'notification_clear_at'     => now(),
            ]);
        }catch(Exception $e) {
            $error = ['error' => [__('Something Went Wrong! Please Try Again.')]];
            return Response::error($error,null,404);
        }

        $success = ['success' => ['Notifications clear successfully!']];
        return Response::success($success,null,200);
    }
}
