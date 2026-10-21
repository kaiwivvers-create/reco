<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('criterion_id')->constrained('criteria')->cascadeOnDelete();
            $table->decimal('value', 16, 4)->default(0);
            $table->timestamps();
            $table->unique(['supplier_id', 'criterion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_scores');
    }
};
