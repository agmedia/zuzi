<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHistoryLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('history_log', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->bigInteger('user_id');
            $table->string('type'); // change, create, delete
            $table->string('target'); // product, order, category, author...
            $table->bigInteger('target_id')->default(0);
            $table->string('title')->nullable();
            $table->longText('changes')->nullable();
            $table->longText('old_model')->nullable();
            $table->longText('new_model')->nullable();
            $table->tinyInteger('badge')->default(0);
            $table->text('comment')->nullable();
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
        Schema::dropIfExists('history_log');
    }
}
