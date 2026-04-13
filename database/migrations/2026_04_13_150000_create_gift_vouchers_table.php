<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('gift_vouchers')) {
            return;
        }

        Schema::create('gift_vouchers', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('order_id')->index();
            $table->string('cart_item_key', 64);
            $table->unsignedBigInteger('action_id')->nullable()->index();
            $table->string('code', 64)->nullable()->unique();
            $table->decimal('amount', 15, 4);
            $table->string('buyer_name')->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_email');
            $table->string('sender_name')->nullable();
            $table->text('message')->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'cart_item_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_vouchers');
    }
};
