<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impact_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_id')->constrained('formulas')->cascadeOnDelete();
            $table->string('triggered_by')->nullable();
            $table->unsignedInteger('affected_contracts')->nullable();
            $table->decimal('current_total', 15, 4)->nullable();
            $table->decimal('new_total', 15, 4)->nullable();
            $table->decimal('difference', 15, 4)->nullable();
            $table->enum('status', ['pending', 'complete', 'failed'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impact_analyses');
    }
};
