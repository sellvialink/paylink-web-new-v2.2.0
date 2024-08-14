<?php

namespace App\Models;

use Illuminate\Support\Str;
use App\Models\Admin\PaymentGateway;
use App\Constants\PaymentGatewayConst;
use Illuminate\Database\Eloquent\Model;
use App\Models\Admin\PaymentGatewayCurrency;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Transaction extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    protected $appends = ['stringStatus'];

    protected $casts = [
        'admin_id'                    => 'integer',
        'user_id'                     => 'integer',
        'user_wallet_id'              => 'integer',
        'payment_gateway_currency_id' => 'integer',
        'trx_id'                      => 'string',
        'request_amount'              => 'decimal:16',
        'available_balance'           => 'decimal:16',
        'payable'                     => 'decimal:16',
        'remark'                      => 'string',
        'status'                      => 'integer',
        'details'                     => 'object',
        'reject_reason'               => 'string',
    ];

    public function scopeMoneyOut($query) {
        return $query->where("type",PaymentGatewayConst::TYPEMONEYOUT);
    }

    public function scopePayInvoice($query) {
        return $query->where("type",PaymentGatewayConst::TYPEINVOICE);
    }

    public function scopePayLink($query) {
        return $query->where("type",PaymentGatewayConst::TYPEPAYLINK);
    }

    public function scopePayProduct($query) {
        return $query->where("type",PaymentGatewayConst::TYPEPRODUCT);
    }

    public function scopeAddSubBalance($query) {
        return $query->where("type",PaymentGatewayConst::TYPEADDSUBTRACTBALANCE);
    }

    public function scopeActive($query) {
        return $query->where("status",PaymentGatewayConst::STATUSSUCCESS);
    }

    public function gateway_currency() {
        return $this->belongsTo(PaymentGatewayCurrency::class,'payment_gateway_currency_id');
    }

    public function getStringStatusAttribute() {
        $status = $this->status;
        $data = [
            'class' => "",
            'value' => "",
        ];
        if($status == PaymentGatewayConst::STATUSSUCCESS) {
            $data = [
                'class'     => "badge badge--success",
                'value'     => __('Success'),
            ];
        }else if($status == PaymentGatewayConst::STATUSPENDING){
            $data = [
                'class'     => "badge badge--warning",
                'value'     => __('Pending'),
            ];
        }

        return (object) $data;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function user_wallet()
    {
        return $this->belongsTo(UserWallet::class, 'user_wallet_id');
    }

    public function currency()
    {
        return $this->belongsTo(PaymentGatewayCurrency::class,'payment_gateway_currency_id');
    }

    public function scopeAuth($query) {
        $query->where("user_id",auth()->user()->id);
    }


    public function charge() {
        return $this->hasOne(TransactionCharge::class,"transaction_id","id");
    }


    public function scopeSearch($query,$data) {
        $data = Str::slug($data);
        return $query->where("trx_id","like","%".$data."%")
                    ->orWhere('type', 'like', '%'.$data.'%')
                    ->orderBy('id',"DESC");

    }

    public function isAuthUser() {
        if($this->user_id === auth()->user()->id) return true;
        return false;
    }


}
