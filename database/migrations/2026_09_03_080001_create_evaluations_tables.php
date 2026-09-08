<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evaluator_id')->constrained('users')->restrictOnDelete();
            $table->date('evaluation_date');
            $table->date('period_start');
            $table->date('period_end');
            foreach (['skills_score','soft_skills_score','management_score','autonomy_score','results_score','overall_score'] as $score) $table->decimal($score, 3, 2)->nullable();
            $table->text('comments')->nullable();
            $table->text('goals')->nullable();
            $table->text('strengths')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->enum('status', ['planned','in_progress','completed','cancelled'])->default('planned');
            $table->text('feedback')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->date('next_evaluation_date')->nullable();
            $table->timestamps();
            $table->index(['employee_id','evaluation_date']);
        });
        Schema::create('evaluation_criteria', function (Blueprint $table) { $table->id(); $table->string('name'); $table->text('description')->nullable(); $table->decimal('weight',5,2)->default(1); $table->boolean('is_active')->default(true); $table->timestamps(); });
        Schema::create('evaluation_goals', function (Blueprint $table) { $table->id(); $table->foreignId('evaluation_id')->constrained()->cascadeOnDelete(); $table->string('title'); $table->text('description')->nullable(); $table->date('due_date')->nullable(); $table->enum('status',['pending','in_progress','achieved','cancelled'])->default('pending'); $table->timestamps(); });
    }
    public function down(): void { Schema::dropIfExists('evaluation_goals'); Schema::dropIfExists('evaluation_criteria'); Schema::dropIfExists('evaluations'); }
};
