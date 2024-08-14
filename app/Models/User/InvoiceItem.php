<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'id'               => 'integer',
        'invoice_item_id' => 'integer',
        'title'            => 'string',
        'qty'             => 'integer',
        'price'           => 'decimal:8',
    ];
}
