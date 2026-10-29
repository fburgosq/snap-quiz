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
        Schema::create('quiz_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('sentence');
            $table->unsignedTinyInteger('blank_count')->default(1);
            $table->text('reason');
            $table->timestamps();

            $table->unique(['quiz_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quiz_items');
    }
};
