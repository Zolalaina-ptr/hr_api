<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveReplacement extends Model
{
    use HasFactory;

    protected $fillable = [
        'leave_id',
        'original_employee_id',
        'replacement_employee_id',
        'start_date',
        'end_date',
        'responsibilities',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'approved_at' => 'datetime',
    ];

    public const STATUSES = ['pending', 'accepted', 'declined', 'cancelled'];

    public function leave(): BelongsTo
    {
        return $this->belongsTo(Leave::class);
    }

    public function originalEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'original_employee_id');
    }

    public function replacementEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'replacement_employee_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}