<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quiz_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_blank_id')->constrained()->cascadeOnDelete();
            $table->string('text', 500);
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->index(['quiz_blank_id', 'is_correct']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_options');
    }
};
