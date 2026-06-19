<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formula_variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formula_id')->constrained('formulas')->cascadeOnDelete();
            $table->string('variable_name');
            $table->text('expression');
            $table->json('ast_json');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formula_variables');
    }
};
