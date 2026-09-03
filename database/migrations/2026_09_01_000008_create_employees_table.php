<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('registration_number')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('France');
            $table->string('gender')->default('other');
            $table->string('nationality')->nullable();
            $table->string('marital_status')->default('single');
            $table->unsignedInteger('children_count')->default(0);
            $table->date('hiring_date');
            $table->string('contract_type')->default('cdi');
            $table->string('status')->default('active');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('positions')->nullOnDelete();
            $table->unsignedInteger('hierarchy_level')->default(1);
            $table->decimal('base_salary', 12, 2)->default(0);
            $table->decimal('hourly_rate', 12, 2)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('iban')->nullable();
            $table->string('bic')->nullable();
            $table->string('social_security_number')->nullable()->unique();
            $table->string('photo_url')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('emergency_contact')->nullable();
            $table->string('emergency_phone')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
