<?php

namespace App\Models;

use App\Constants\PaymentGatewayConst;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'id'              => 'integer',
        'user_id'         => 'integer',
        'currency'        => 'string',
        'currency_name'   => 'string',
        'currency_symbol' => 'string',
        'country'         => 'string',
        'product_name'    => 'string',
        'slug'            => 'string',
        'desc'            => 'string',
        'price'           => 'decimal:16',
        'status'          => 'integer',
    ];

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
        }else{
            $data = [
                'class'     => "badge badge--warning",
                'value'     => __('Inactive'),
            ];
        }
        return (object) $data;
    }

    public function scopeAuth($query) {
        $query->where("user_id",auth()->user()->id);
    }

    public function productLinks(){
        return $this->hasMany(ProductLink::class);
    }

}
