<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountNoticeMailLogsTable extends Migration
{
    public function up()
    {
        Schema::create('account_notice_mail_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('email');
            $table->char('notice_hash', 40);
            $table->string('notice_title')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'notice_hash'], 'account_notice_mail_logs_user_hash_unique');
            $table->index('notice_hash');
        });
    }

    public function down()
    {
        Schema::dropIfExists('account_notice_mail_logs');
    }
}
