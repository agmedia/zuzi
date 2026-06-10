<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        if (! Schema::hasColumn('products', 'polica')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('polica', 64)->nullable()->after('sku');
            });

            return;
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `products` MODIFY `polica` VARCHAR(64) NULL');
        }
    }

    public function down(): void
    {
        //
    }
};
