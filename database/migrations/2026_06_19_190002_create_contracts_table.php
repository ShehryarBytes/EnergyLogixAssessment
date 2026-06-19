<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name');
            $table->decimal('annual_usage', 15, 4);
            $table->decimal('contract_value', 15, 4);
            $table->unsignedInteger('contract_length'); // months
            $table->decimal('risk_score', 5, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
