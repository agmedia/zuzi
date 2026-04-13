<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('product_actions')) {
            return;
        }

        Schema::create('product_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('type', 32);
            $table->decimal('discount', 15, 4)->default(0);
            $table->string('group');
            $table->text('links')->nullable();
            $table->timestamp('date_start')->nullable();
            $table->timestamp('date_end')->nullable();
            $table->string('badge')->nullable();
            $table->decimal('min_cart', 15, 4)->nullable();
            $table->text('data')->nullable();
            $table->string('coupon')->nullable()->index();
            $table->boolean('quantity')->default(0);
            $table->boolean('lock')->default(0);
            $table->boolean('logged')->default(0);
            $table->unsignedInteger('uses_customer')->default(1);
            $table->unsignedInteger('viewed')->default(0);
            $table->unsignedInteger('clicked')->default(0);
            $table->boolean('status')->default(0)->index();
            $table->timestamps();

            $table->index('group');
            $table->index(['group', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_actions');
    }
};
