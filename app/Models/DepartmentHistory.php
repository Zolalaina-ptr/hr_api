<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentHistory extends Model
{
    protected $fillable = [
        'department_id',
        'effective_date',
        'previous_manager_id',
        'new_manager_id',
        'previous_budget',
        'new_budget',
        'change_reason',
        'changed_by',
        'notes',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'previous_budget' => 'decimal:2',
        'new_budget' => 'decimal:2',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function previousManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'previous_manager_id');
    }

    public function newManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'new_manager_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
