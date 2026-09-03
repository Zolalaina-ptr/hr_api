<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_id')->constrained('leaves')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->string('action');
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->text('comment')->nullable();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['leave_id', 'action']);
            $table->index(['employee_id', 'performed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};