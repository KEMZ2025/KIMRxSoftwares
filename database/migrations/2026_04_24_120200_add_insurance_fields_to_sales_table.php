<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'insurer_id')) {
                $table->foreignId('insurer_id')->nullable()->after('customer_id')->constrained('insurers')->nullOnDelete();
            }

            if (!Schema::hasColumn('sales', 'insurance_plan_name')) {
                $table->string('insurance_plan_name')->nullable()->after('payment_type');
            }

            if (!Schema::hasColumn('sales', 'insurance_member_number')) {
                $table->string('insurance_member_number')->nullable()->after('insurance_plan_name');
            }

            if (!Schema::hasColumn('sales', 'insurance_card_number')) {
                $table->string('insurance_card_number')->nullable()->after('insurance_member_number');
            }

            if (!Schema::hasColumn('sales', 'insurance_authorization_number')) {
                $table->string('insurance_authorization_number')->nullable()->after('insurance_card_number');
            }

            if (!Schema::hasColumn('sales', 'insurance_claim_status')) {
                $table->string('insurance_claim_status')->nullable()->after('insurance_authorization_number');
            }

            if (!Schema::hasColumn('sales', 'insurance_status_notes')) {
                $table->text('insurance_status_notes')->nullable()->after('insurance_claim_status');
            }

            if (!Schema::hasColumn('sales', 'insurance_covered_amount')) {
                $table->decimal('insurance_covered_amount', 14, 2)->default(0)->after('amount_received');
            }

            if (!Schema::hasColumn('sales', 'patient_copay_amount')) {
                $table->decimal('patient_copay_amount', 14, 2)->default(0)->after('insurance_covered_amount');
            }

            if (!Schema::hasColumn('sales', 'insurance_balance_due')) {
                $table->decimal('insurance_balance_due', 14, 2)->default(0)->after('patient_copay_amount');
            }

            if (!Schema::hasColumn('sales', 'upfront_amount_paid')) {
                $table->decimal('upfront_amount_paid', 14, 2)->default(0)->after('insurance_balance_due');
            }

            if (!Schema::hasColumn('sales', 'insurance_submitted_at')) {
                $table->dateTime('insurance_submitted_at')->nullable()->after('approved_at');
            }

            if (!Schema::hasColumn('sales', 'insurance_approved_at')) {
                $table->dateTime('insurance_approved_at')->nullable()->after('insurance_submitted_at');
            }

            if (!Schema::hasColumn('sales', 'insurance_rejected_at')) {
                $table->dateTime('insurance_rejected_at')->nullable()->after('insurance_approved_at');
            }

            if (!Schema::hasColumn('sales', 'insurance_paid_at')) {
                $table->dateTime('insurance_paid_at')->nullable()->after('insurance_rejected_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            foreach ([
                'insurer_id',
                'insurance_plan_name',
                'insurance_member_number',
                'insurance_card_number',
                'insurance_authorization_number',
                'insurance_claim_status',
                'insurance_status_notes',
                'insurance_covered_amount',
                'patient_copay_amount',
                'insurance_balance_due',
                'upfront_amount_paid',
                'insurance_submitted_at',
                'insurance_approved_at',
                'insurance_rejected_at',
                'insurance_paid_at',
            ] as $column) {
                if (Schema::hasColumn('sales', $column)) {
                    if ($column === 'insurer_id') {
                        $table->dropConstrainedForeignId($column);
                    } else {
                        $table->dropColumn($column);
                    }
                }
            }
        });
    }
};
