<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // This table is intentionally append-only — records must never be updated after creation.
        Schema::create('commission_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained('contracts')->restrictOnDelete();
            $table->foreignId('formula_id')->constrained('formulas')->restrictOnDelete();
            $table->json('input_values');       // snapshot of contract values at calculation time
            $table->json('calculation_steps'); // step-by-step AST walk result for audit trail
            $table->decimal('result', 15, 4);
            $table->timestamp('calculated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_calculations');
    }
};
