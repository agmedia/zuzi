<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateProductIdentifierReservationsTable extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_identifier_allocation_locks')) {
            Schema::create('product_identifier_allocation_locks', function (Blueprint $table) {
                $table->unsignedTinyInteger('id')->primary();
            });
        }

        DB::table('product_identifier_allocation_locks')->updateOrInsert(['id' => 1]);

        if (! Schema::hasTable('product_identifier_reservations')) {
            Schema::create('product_identifier_reservations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('token')->unique();
                $table->unsignedBigInteger('sku')->unique();
                $table->unsignedBigInteger('itemid')->unique();
                $table->timestamp('expires_at')->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_identifier_reservations');
        Schema::dropIfExists('product_identifier_allocation_locks');
    }
}
