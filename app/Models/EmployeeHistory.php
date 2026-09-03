<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeHistory extends Model
{
    protected $fillable = [
        'employee_id',
        'effective_date',
        'previous_position',
        'new_position',
        'previous_salary',
        'new_salary',
        'change_reason',
        'changed_by',
        'notes',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'previous_salary' => 'decimal:2',
        'new_salary' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
