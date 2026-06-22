<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasColumn('orders', 'pelion_status')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('pelion_status', 32)->nullable()->index();
            });
        }

        if (! Schema::hasColumn('orders', 'pelion_invoice_number')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('pelion_invoice_number')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('orders', 'pelion_invoice_date')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->date('pelion_invoice_date')->nullable();
            });
        }

        if (! Schema::hasColumn('orders', 'pelion_imported_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('pelion_imported_at')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('orders', 'pelion_invoiced_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('pelion_invoiced_at')->nullable()->index();
            });
        }

        if (! Schema::hasColumn('orders', 'pelion_error')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->text('pelion_error')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            foreach ([
                'pelion_status',
                'pelion_invoice_number',
                'pelion_invoice_date',
                'pelion_imported_at',
                'pelion_invoiced_at',
                'pelion_error',
            ] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
