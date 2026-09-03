<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
        'days_per_year',
        'is_paid',
        'is_sick',
        'requires_justification',
        'max_days',
        'min_days_notice',
        'color_code',
        'is_active',
    ];

    protected $casts = [
        'days_per_year' => 'decimal:2',
        'is_paid' => 'boolean',
        'is_sick' => 'boolean',
        'requires_justification' => 'boolean',
        'max_days' => 'integer',
        'min_days_notice' => 'integer',
        'is_active' => 'boolean',
    ];

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

    public function balances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    public function scopeSick($query)
    {
        return $query->where('is_sick', true);
    }
}