<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceHoliday extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'type',
        'is_recurring',
        'is_paid',
        'region',
        'description',
        'is_active',
    ];

    protected $casts = [
        'date' => 'date',
        'is_recurring' => 'boolean',
        'is_paid' => 'boolean',
        'is_active' => 'boolean',
    ];

    public const TYPES = [
        'public',
        'religious',
        'company',
        'optional',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('type', 'public');
    }

    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    public function scopeForYear($query, $year)
    {
        return $query->whereYear('date', $year);
    }

    public function scopeForRegion($query, ?string $region)
    {
        return $query->where(function ($q) use ($region) {
            $q->whereNull('region')->orWhere('region', $region);
        });
    }
}