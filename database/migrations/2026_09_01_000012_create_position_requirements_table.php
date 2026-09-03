<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('position_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->string('requirement_type');
            $table->string('requirement_name');
            $table->text('description')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->integer('years_experience')->nullable();
            $table->string('level')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['position_id', 'requirement_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('position_requirements');
    }
};
