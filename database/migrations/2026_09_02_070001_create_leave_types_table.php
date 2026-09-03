<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->decimal('days_per_year', 5, 2)->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('is_sick')->default(false);
            $table->boolean('requires_justification')->default(false);
            $table->unsignedInteger('max_days')->nullable();
            $table->unsignedInteger('min_days_notice')->default(0);
            $table->string('color_code', 7)->default('#3490dc');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};