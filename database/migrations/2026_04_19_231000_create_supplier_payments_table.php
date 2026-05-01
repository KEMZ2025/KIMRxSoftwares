<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained();
            $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_method');
            $table->decimal('amount', 14, 2);
            $table->string('reference_number')->nullable();
            $table->dateTime('payment_date');
            $table->string('status')->default('paid');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        DB::table('purchases')
            ->where('amount_paid', '>', 0)
            ->orderBy('id')
            ->chunkById(100, function ($purchases) {
                foreach ($purchases as $purchase) {
                    $alreadyLogged = DB::table('supplier_payments')
                        ->where('purchase_id', $purchase->id)
                        ->exists();

                    if ($alreadyLogged) {
                        continue;
                    }

                    $paymentDate = $purchase->created_at ?: ($purchase->purchase_date . ' 00:00:00');

                    DB::table('supplier_payments')->insert([
                        'client_id' => $purchase->client_id,
                        'branch_id' => $purchase->branch_id,
                        'supplier_id' => $purchase->supplier_id,
                        'purchase_id' => $purchase->id,
                        'paid_by' => $purchase->created_by,
                        'payment_method' => 'cheque',
                        'amount' => $purchase->amount_paid,
                        'reference_number' => null,
                        'payment_date' => $paymentDate,
                        'status' => 'paid',
                        'notes' => 'Backfilled from invoice entry amount already paid.',
                        'created_at' => $purchase->created_at ?: now(),
                        'updated_at' => $purchase->updated_at ?: now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
