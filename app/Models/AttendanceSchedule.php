<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'start_time',
        'end_time',
        'break_minutes',
        'work_days_per_week',
        'work_days',
        'daily_hours',
        'weekly_hours',
        'overtime_threshold',
        'late_tolerance_minutes',
        'is_default',
        'is_flexible',
        'is_active',
    ];

    protected $casts = [
        'start_time' => 'string',
        'end_time' => 'string',
        'work_days' => 'array',
        'is_default' => 'boolean',
        'is_flexible' => 'boolean',
        'is_active' => 'boolean',
        'break_minutes' => 'integer',
        'work_days_per_week' => 'integer',
        'daily_hours' => 'decimal:2',
        'weekly_hours' => 'decimal:2',
        'overtime_threshold' => 'decimal:2',
        'late_tolerance_minutes' => 'decimal:2',
    ];

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class, 'schedule_id');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(AttendanceException::class, 'schedule_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}