<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'schedule_id',
        'date',
        'clock_in',
        'clock_out',
        'work_hours',
        'break_hours',
        'overtime_hours',
        'late_minutes',
        'status',
        'absence_reason',
        'ip_address',
        'latitude',
        'longitude',
        'notes',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in' => 'datetime',
        'clock_out' => 'datetime',
        'approved_at' => 'datetime',
        'work_hours' => 'decimal:2',
        'break_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
        'late_minutes' => 'decimal:2',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public const STATUSES = [
        'present',
        'absent',
        'on_leave',
        'training',
        'remote',
        'late',
        'half_day',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(AttendanceSchedule::class, 'schedule_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPresent(): bool
    {
        return $this->status === 'present';
    }

    public function isOnLeave(): bool
    {
        return $this->status === 'on_leave';
    }

    public function isOpen(): bool
    {
        return $this->clock_in !== null && $this->clock_out === null;
    }

    public function getDurationInMinutes(): int
    {
        if (! $this->clock_in || ! $this->clock_out) {
            return 0;
        }

        return (int) round($this->clock_in->diffInMinutes($this->clock_out));
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }
}