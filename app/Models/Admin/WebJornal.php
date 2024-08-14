<?php

namespace App\Models\Admin;

use App\Models\CategoryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebJornal extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts =[
        'id'           => 'integer',
        'admin_id'     => 'integer',
        'title'        => 'object',
        'heading'        => 'object',
        'details'      => 'object',
        'tags'         => 'object',
        'slug'         => 'string',
        'image'        => 'string',
        'status'       => 'integer',
    ];

    public function category(){
        return $this->belongsTo(CategoryType::class, 'category_type_id', 'id');
    }

    public function admin() {
        return $this->belongsTo(Admin::class);
    }
}
