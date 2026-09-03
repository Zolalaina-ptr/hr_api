<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->date('effective_date');
            $table->foreignId('previous_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->foreignId('new_manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->decimal('previous_budget', 15, 2)->nullable();
            $table->decimal('new_budget', 15, 2)->nullable();
            $table->string('change_reason')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('department_histories');
    }
};
