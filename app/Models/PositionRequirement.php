<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionRequirement extends Model
{
    protected $fillable = [
        'position_id',
        'requirement_type',
        'requirement_name',
        'description',
        'is_mandatory',
        'years_experience',
        'level',
        'notes',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
