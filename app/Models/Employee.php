<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'birth_date',
        'birth_place',
        'address',
        'city',
        'postal_code',
        'country',
        'gender',
        'nationality',
        'marital_status',
        'children_count',
        'hiring_date',
        'contract_type',
        'status',
        'department_id',
        'position_id',
        'hierarchy_level',
        'base_salary',
        'hourly_rate',
        'bank_name',
        'iban',
        'bic',
        'social_security_number',
        'photo_url',
        'user_id',
        'manager_id',
        'emergency_contact',
        'emergency_phone',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'hiring_date' => 'date',
        'base_salary' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function skills(): HasMany
    {
        return $this->hasMany(EmployeeSkill::class);
    }

    public function languages(): HasMany
    {
        return $this->hasMany(EmployeeLanguage::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(EmployeeHistory::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendanceExceptions(): HasMany
    {
        return $this->hasMany(AttendanceException::class);
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }
}
