<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMatchPredictionsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('match_predictions')) {
            return;
        }

        Schema::create('match_predictions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 255)->unique();
            $table->unsignedTinyInteger('croatia_goals');
            $table->unsignedTinyInteger('england_goals');
            $table->unsignedTinyInteger('first_goal_minute')->nullable();
            $table->unsignedTinyInteger('yellow_cards_total')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('accepted_rules')->default(false);
            $table->boolean('accepted_privacy')->default(false);
            $table->boolean('newsletter_consent')->default(false);
            $table->integer('winner_score')->nullable();
            $table->boolean('is_winner')->default(false);
            $table->timestamp('contacted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('match_predictions');
    }
}
