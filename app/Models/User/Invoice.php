<?php

namespace App\Models\User;

use App\Models\User;
use App\Constants\PaymentGatewayConst;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'id'            => 'integer',
        'user_id'       => 'integer',
        'currency'      => 'string',
        'currency_name' => 'string',
        'country'       => 'string',
        'invoice_no'    => 'string',
        'token'        => 'string',
        'title'        => 'string',
        'name'         => 'string',
        'email'        => 'string',
        'phone'        => 'string',
        'qty'          => 'integer',
        'amount'       => 'decimal:16',
        'status'       => 'integer',
    ];

    public function scopeStatus($query, $status){
        $query->where('status',$status);
    }

    public function invoiceItems(){
        return $this->hasMany(InvoiceItem::class,'invoice_item_id', 'id');
    }

    public function scopeAuth($query) {
        $query->where("user_id",auth()->user()->id);
    }

    public function user(){
        return $this->belongsTo(User::class);
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
                'value'     => __('Paid'),
            ];
        }else if($status == PaymentGatewayConst::STATUSPENDING) {
            $data = [
                'class'     => "badge badge--warning",
                'value'     => __('Unpaid'),
            ];
        }else if($status == PaymentGatewayConst::STATUSHOLD) {
            $data = [
                'class'     => "badge badge--warning",
                'value'     => __('Draft'),
            ];
        }
        return (object) $data;
    }
}
