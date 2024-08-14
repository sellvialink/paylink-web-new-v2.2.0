<?php

use App\Constants\PaymentGatewayConst;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('user_wallet_id')->nullable();
            $table->unsignedBigInteger("payment_gateway_currency_id")->nullable();
            $table->foreignId('payment_link_id')->nullable()->constrained('payment_links')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->cascadeOnDelete();
            $table->enum("type",[
                PaymentGatewayConst::TYPEBONUS,
                PaymentGatewayConst::TYPEADDSUBTRACTBALANCE,
                PaymentGatewayConst::TYPEPAYLINK,
                PaymentGatewayConst::TYPEINVOICE,
                PaymentGatewayConst::TYPEMONEYOUT,
                PaymentGatewayConst::TYPEPRODUCT,
            ]);
            $table->string("trx_id")->comment("Transaction ID");
            $table->decimal('request_amount', 28, 16)->default(0);
            $table->decimal('payable', 28, 16)->default(0);
            $table->decimal('conversion_payable', 28, 16)->default(0);
            $table->decimal('request_amount_admin', 28, 16)->default(0);
            $table->decimal('available_balance', 28, 16)->default(0);
            $table->string("remark")->nullable();
            $table->text("details")->nullable();
            $table->text("reject_reason")->nullable();
            $table->tinyInteger("status")->default(0)->comment("0: Default, 1: Success, 2: Pending, 3: Hold, 4: Rejected, 5: Payment Pending, 6: Complete, 7: Cancel Request, 8: Cancel Buy User");
            $table->enum("attribute",[
                PaymentGatewayConst::SEND,
                PaymentGatewayConst::RECEIVED,
            ]);
            $table->timestamps();

            $table->foreign("admin_id")->references("id")->on("admins")->onDelete("cascade")->onUpdate("cascade");
            $table->foreign("user_id")->references("id")->on("users")->onDelete("cascade")->onUpdate("cascade");
            $table->foreign("user_wallet_id")->references("id")->on("user_wallets")->onDelete("cascade")->onUpdate("cascade");
            $table->foreign("payment_gateway_currency_id")->references("id")->on("payment_gateway_currencies")->onDelete("cascade")->onUpdate("cascade");
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
