<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('pelion_items')) {
            Schema::create('pelion_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('item_id')->unique();
                $table->string('item_barcode', 32)->index();
                $table->string('item_code', 64)->nullable();
                $table->string('item_name')->nullable();
                $table->string('item_group_id', 32)->nullable();
                $table->string('item_active', 8)->nullable();
                $table->string('item_type', 64)->nullable();
                $table->decimal('item_price', 15, 4)->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'isbn') && ! $this->hasIndex('products', 'products_isbn_index')) {
                    $table->index('isbn', 'products_isbn_index');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pelion_items');
    }

    private function hasIndex(string $table, string $index): bool
    {
        return count(DB::select('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$index])) > 0;
    }
};
