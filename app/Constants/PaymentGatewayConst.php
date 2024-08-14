<?php
namespace App\Constants;
use App\Models\UserWallet;
use Illuminate\Support\Str;

class PaymentGatewayConst {

    const AUTOMATIC = "AUTOMATIC";
    const MANUAL    = "MANUAL";
    const MONEYOUT  = "Money Out";
    const ACTIVE    =  true;
    const ADDMONEY  = "Add Money";

    const LINK_TYPE_PAY = 'pay';
    const LINK_TYPE_SUB = 'sub';
    const TYPE_GATEWAY_PAYMENT = 'payment_gateway';
    const TYPE_CARD_PAYMENT = 'card_payment';

    const CALLBACK_HANDLE_INTERNAL  = "CALLBACK_HANDLE_INTERNAL";

    const FIAT          = "FIAT";
    const CRYPTO        = "CRYPTO";

    const TYPEBONUS              = "BONUS";
    const TYPEADDSUBTRACTBALANCE = "ADD-SUBTRACT-BALANCE";
    const TYPEPAYLINK            = "PAY-LINK";
    const TYPEMONEYOUT           = "MONEY-OUT";
    const TYPEINVOICE            = "PAY-INVOICE";
    const TYPEPRODUCT            = "PAY-PRODUCT";

    const STATUSSUCCESS     = 1;
    const STATUSPENDING     = 2;
    const STATUSHOLD        = 3;
    const STATUSREJECTED    = 4;
    const STATUSWAITING     = 5;

    const PAYPAL = 'paypal';
    const STRIPE = 'stripe';
    const MANUA_GATEWAY = 'manual';
    const FLUTTERWAVE = 'flutterwave';
    const RAZORPAY = 'razorpay';
    const SSLCOMMERZ = 'sslcommerz';
    const QRPAY = 'qrpay';
    const BALANCE = 'balance';
    const PAGADITO = 'pagadito';
    const COIN_GATE      = 'coingate';
    const PERFECT_MONEY          = 'perfect-money';

    const ENV_SANDBOX       = "SANDBOX";
    const ENV_PRODUCTION    = "PRODUCTION";


    const SEND = "SEND";
    const RECEIVED = "RECEIVED";

    public static function add_money_slug() {
        return Str::slug(self::ADDMONEY);
    }
    public static function paylink_slug(){
        return Str::slug(self::TYPEPAYLINK);
    }

    public static function money_out_slug() {
        return Str::slug(self::MONEYOUT);
    }

    public static function registerWallet() {
        return [
            'web'       => UserWallet::class,
            'api'       => UserWallet::class,
        ];
    }

    public static function register($alias = null) {
        $gateway_alias  = [
            self::PAYPAL        => "paypalInit",
            self::STRIPE        => "stripeInit",
            self::MANUA_GATEWAY => "manualInit",
            self::FLUTTERWAVE   => 'flutterwaveInit',
            self::COIN_GATE     => 'coinGateInit',
            self::SSLCOMMERZ    => 'sslcommerzInit',
            self::QRPAY         => "qrpayInit",
            self::RAZORPAY      => 'razorInit',
            self::PAGADITO      => 'pagaditoInit',
            self::COIN_GATE     => 'coinGateInit',
            self::PERFECT_MONEY => 'perfectMoneyInit'
        ];

        if($alias == null) {
            return $gateway_alias;
        }

        if(array_key_exists($alias,$gateway_alias)) {
            return $gateway_alias[$alias];
        }
        return "init";
    }

    const REDIRECT_USING_HTML_FORM = "REDIRECT_USING_HTML_FORM";

    public static function registerRedirection() {
        return [
            'web'       => [
                'return_url'    => 'user.add.money.payment.success',
                'cancel_url'    => 'user.add.money.payment.cancel',
                'callback_url'  => 'user.add.money.payment.callback',
                'btn_pay'       => 'payment.btn.pay',
            ],
            'api'       => [
                'return_url'    => 'api.user.add.money.payment.success',
                'cancel_url'    => 'api.user.add.money.payment.cancel',
                'callback_url'  => 'user.add.money.payment.callback',
            ],
        ];
    }

    const APP       = "APP";

    public static function apiAuthenticateGuard() {
        return [
            'api'   => 'web',
        ];
    }

    public static function registerGatewayRecognization() {
        return [
            'isCoinGate'    => self::COIN_GATE,
            'isPerfectMoney' => self::PERFECT_MONEY,
            'isRazorpay'        => self::RAZORPAY,
        ];
    }

}
