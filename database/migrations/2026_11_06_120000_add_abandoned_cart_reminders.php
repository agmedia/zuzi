<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddAbandonedCartReminders extends Migration
{
    public function up()
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'unfinished_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dateTime('unfinished_at')->nullable()->index();
            });
        }

        if (! Schema::hasTable('abandoned_cart_reminders')) {
            Schema::create('abandoned_cart_reminders', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('order_id');
                $table->unsignedTinyInteger('sequence');
                $table->string('source', 16);
                $table->string('recipient_email', 191);
                $table->char('recipient_key', 64)->index();
                $table->dateTime('scheduled_for');
                $table->dateTime('attempted_at')->nullable();
                $table->dateTime('sent_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->unique(['order_id', 'sequence'], 'abandoned_cart_order_sequence_unique');
                $table->index(['recipient_key', 'attempted_at'], 'abandoned_cart_recipient_attempt_index');
            });
        }

        if (Schema::hasTable('settings') && ! DB::table('settings')
            ->where('code', 'order')
            ->where('key', 'abandoned_cart_reminders_started_at')
            ->whereNull('user_id')
            ->exists()) {
            $activatedAt = now();

            DB::table('settings')->insert([
                'user_id' => null,
                'code' => 'order',
                'key' => 'abandoned_cart_reminders_started_at',
                'value' => $activatedAt->format('Y-m-d H:i:s'),
                'json' => false,
                'created_at' => $activatedAt,
                'updated_at' => $activatedAt,
            ]);
        }
    }

    public function down()
    {
        // Keep delivery evidence and the activation time during code rollbacks.
        // Removing either could cause old reminders to be sent again.
    }
}
