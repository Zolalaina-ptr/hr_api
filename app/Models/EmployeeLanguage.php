<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLanguage extends Model
{
    protected $fillable = [
        'employee_id',
        'language',
        'proficiency_level',
        'is_native',
    ];

    protected $casts = [
        'is_native' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
