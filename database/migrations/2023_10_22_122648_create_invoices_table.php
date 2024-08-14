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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('currency');
            $table->string('currency_symbol');
            $table->string('currency_name');
            $table->string('country');
            $table->string('invoice_no');
            $table->string('token')->unique();
            $table->string('title')->nullable();
            $table->string('name', 60);
            $table->string('email', 120);
            $table->string('phone', 20);
            $table->integer('qty');
            $table->decimal('amount', 28,16,true);
            $table->enum('status', [1,2,3])->default(2)->comment('1=Paid, 2=Unpaid, 3=Draft');
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
        Schema::dropIfExists('invoices');
    }
};
