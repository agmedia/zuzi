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

        if (! Schema::hasColumn('products', 'decrease')) {
            Schema::table('products', function (Blueprint $table) {
                $table->integer('decrease')->default(1)->after('quantity');
            });
        }

        DB::table('products')
            ->where('decrease', 0)
            ->update([
                'decrease' => 1,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        //
    }
};
