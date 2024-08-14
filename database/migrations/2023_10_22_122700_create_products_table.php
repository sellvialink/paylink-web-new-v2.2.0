<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('currency');
            $table->string('currency_symbol');
            $table->string('currency_name');
            $table->string('country');
            $table->string('product_name');
            $table->string('slug')->unique();
            $table->string('image')->nullable();
            $table->text('desc')->nullable();
            $table->decimal('price', 28,16,true);
            $table->enum('status', [1,2])->default(2)->comment('1=Active, 2=Inactive');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('products');
    }
};
