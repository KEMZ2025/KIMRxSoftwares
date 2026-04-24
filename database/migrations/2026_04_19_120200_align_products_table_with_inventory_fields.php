<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('products', 'client_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedBigInteger('client_id')->nullable()->after('id')->index();
            });
        }

        if (!Schema::hasColumn('products', 'branch_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedBigInteger('branch_id')->nullable()->after('client_id')->index();
            });
        }

        if (!Schema::hasColumn('products', 'category_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id')->nullable()->after('branch_id')->index();
            });
        }

        if (!Schema::hasColumn('products', 'unit_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedBigInteger('unit_id')->nullable()->after('category_id')->index();
            });
        }

        if (!Schema::hasColumn('products', 'strength')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('strength')->nullable()->after('name');
            });
        }

        if (!Schema::hasColumn('products', 'barcode')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('barcode')->nullable()->after('strength');
            });
        }

        if (!Schema::hasColumn('products', 'purchase_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('purchase_price', 14, 2)->default(0)->after('selling_price');
            });

            if (Schema::hasColumn('products', 'buying_price')) {
                DB::table('products')->update([
                    'purchase_price' => DB::raw('buying_price'),
                ]);
            }
        }

        if (!Schema::hasColumn('products', 'retail_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('retail_price', 14, 2)->default(0)->after('purchase_price');
            });

            if (Schema::hasColumn('products', 'selling_price')) {
                DB::table('products')->update([
                    'retail_price' => DB::raw('selling_price'),
                ]);
            }
        }

        if (!Schema::hasColumn('products', 'wholesale_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->decimal('wholesale_price', 14, 2)->default(0)->after('retail_price');
            });

            if (Schema::hasColumn('products', 'selling_price')) {
                DB::table('products')->update([
                    'wholesale_price' => DB::raw('selling_price'),
                ]);
            }
        }

        if (!Schema::hasColumn('products', 'track_batch')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('track_batch')->default(true)->after('wholesale_price');
            });
        }

        if (!Schema::hasColumn('products', 'track_expiry')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('track_expiry')->default(true)->after('track_batch');
            });
        }

        if (!Schema::hasColumn('products', 'is_active')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('is_active')->default(true)->after('track_expiry');
            });
        }
    }

    public function down(): void
    {
        $columns = [];

        foreach ([
            'client_id',
            'branch_id',
            'category_id',
            'unit_id',
            'strength',
            'barcode',
            'purchase_price',
            'retail_price',
            'wholesale_price',
            'track_batch',
            'track_expiry',
            'is_active',
        ] as $column) {
            if (Schema::hasColumn('products', $column)) {
                $columns[] = $column;
            }
        }

        if (!empty($columns)) {
            Schema::table('products', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
