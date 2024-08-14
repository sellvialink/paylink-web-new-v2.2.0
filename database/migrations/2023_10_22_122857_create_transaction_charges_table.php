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
        Schema::create('transaction_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId("transaction_id")->constrained('transactions')->cascadeOnDelete();
            $table->decimal('percent_charge', 28, 16)->default(0);
            $table->decimal('fixed_charge', 28, 16)->default(0);
            $table->decimal('total_charge', 28, 16)->default(0);
            $table->decimal('conversion_charge', 28, 16)->default(0);
            $table->decimal('conversion_admin_charge', 28, 16)->default(0);
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
        Schema::dropIfExists('transaction_charges');
    }
};
