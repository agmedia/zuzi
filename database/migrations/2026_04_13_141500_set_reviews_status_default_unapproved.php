<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetReviewsStatusDefaultUnapproved extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        DB::statement("ALTER TABLE `reviews` MODIFY `status` TINYINT(1) NOT NULL DEFAULT 0");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        DB::statement("ALTER TABLE `reviews` MODIFY `status` TINYINT(1) NOT NULL DEFAULT 1");
    }
}
