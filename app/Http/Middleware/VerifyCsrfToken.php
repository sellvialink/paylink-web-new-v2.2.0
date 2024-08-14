<?php

namespace App\Http\Middleware;

use App\Constants\PaymentGatewayConst;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'user/username/check',
        'user/check/email',

        // SSL Commerze
        // Payment Link
        'payment-link/payment/success/sslcommerz',
        'payment-link/payment/fail/sslcommerz',
        'payment-link/payment/cancel/sslcommerz',

        // Invoice
        'invoice/payment/success/sslcommerz',
        'invoice/payment/fail/sslcommerz',
        'invoice/payment/cancel/sslcommerz',

        // Product
        'product-link/payment/success/sslcommerz',
        'product-link/payment/fail/sslcommerz',
        'product-link/payment/cancel/sslcommerz',

        '/payment-link/payment/success/response/'  . PaymentGatewayConst::RAZORPAY,
        '/payment-link/payment/cancel/response/'  . PaymentGatewayConst::RAZORPAY,

        '/invoice/payment/success/response/'  . PaymentGatewayConst::RAZORPAY,
        '/invoice/payment/cancel/response/'  . PaymentGatewayConst::RAZORPAY,

        '/product-link/payment/success/response/'  . PaymentGatewayConst::RAZORPAY,
        '/product-link/payment/cancel/response/'  . PaymentGatewayConst::RAZORPAY,
    ];
}
