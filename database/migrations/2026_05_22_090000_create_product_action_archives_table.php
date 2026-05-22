<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProductActionArchivesTable extends Migration
{
    public function up()
    {
        Schema::create('product_action_archives', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('product_action_id')->nullable()->unique();
            $table->string('title')->nullable();
            $table->string('type', 10)->nullable();
            $table->integer('discount')->default(0);
            $table->string('group')->nullable()->index();
            $table->text('links')->nullable();
            $table->dateTime('date_start')->nullable();
            $table->dateTime('date_end')->nullable();
            $table->text('data')->nullable();
            $table->string('coupon')->nullable()->index();
            $table->boolean('quantity')->default(0);
            $table->boolean('lock')->default(0);
            $table->boolean('status')->default(0);
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('product_action_archives');
    }
}
