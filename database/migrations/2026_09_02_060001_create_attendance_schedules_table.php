<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('break_minutes')->default(60);
            $table->unsignedSmallInteger('work_days_per_week')->default(5);
            $table->json('work_days')->nullable();
            $table->decimal('daily_hours', 5, 2)->default(8.00);
            $table->decimal('weekly_hours', 5, 2)->default(40.00);
            $table->decimal('overtime_threshold', 5, 2)->default(8.00);
            $table->decimal('late_tolerance_minutes', 5, 2)->default(10.00);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_flexible')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_schedules');
    }
};