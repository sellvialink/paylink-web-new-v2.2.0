<?php

namespace App\Models;

use App\Constants\PaymentGatewayConst;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductLink extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'id'              => 'integer',
        'user_id'         => 'integer',
        'product_id'      => 'integer',
        'currency'        => 'string',
        'currency_name'   => 'string',
        'currency_symbol' => 'string',
        'country'         => 'string',
        'token'           => 'string',
        'price'           => 'decimal:16',
        'qty'             => 'integer',
        'status'          => 'integer',
    ];

    public function scopeAuth($query) {
        $query->where("user_id",auth()->user()->id);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }

    public function product(){
        return $this->belongsTo(Product::class);
    }

    public function getAmountValueAttribute(){
        $price = $this->price;
        $qty = $this->qty;

        $amount = ($price*$qty);

        return number_format($amount, 2, '.', '');
        // return get_amount($amount);
    }

    public function scopeStatus($query, $status){
        $query->where('status',$status);
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
                'value'     => __('Active'),
            ];
        }else if($status == PaymentGatewayConst::STATUSPENDING) {
            $data = [
                'class'     => "badge badge--warning",
                'value'     => __('Inactive'),
            ];
        }
        return (object) $data;
    }
}
