<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('skill_name');
            $table->string('proficiency_level')->default('intermediate');
            $table->unsignedInteger('years_experience')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'skill_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_skills');
    }
};
