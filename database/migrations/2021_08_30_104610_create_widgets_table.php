<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWidgetsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('widget_groups', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('template')->index();
            $table->string('type')->nullable();
            $table->string('title');
            $table->string('slug');
            $table->string('width')->nullable();
            $table->boolean('status')->unsigned()->default(0);
            $table->timestamps();
        });

        Schema::create('widgets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('group_id')->unsigned()->index();
            $table->string('title')->index();
            $table->text('subtitle')->nullable();
            $table->longText('description')->nullable();
            $table->text('data')->nullable();
            $table->string('image')->nullable();
            $table->string('link')->nullable();
            $table->integer('link_id')->nullable();
            $table->string('url')->nullable();
            $table->string('badge')->nullable();
            $table->string('width')->nullable();
            $table->integer('sort_order')->unsigned()->default(0);
            $table->boolean('status')->default(0);
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
        Schema::dropIfExists('widget_groups');
        Schema::dropIfExists('widgets');
    }
}
