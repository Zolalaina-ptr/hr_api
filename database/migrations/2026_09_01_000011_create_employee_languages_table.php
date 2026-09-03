<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_languages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('language');
            $table->string('proficiency_level')->default('intermediate');
            $table->boolean('is_native')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_languages');
    }
};
