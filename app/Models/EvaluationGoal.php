<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class EvaluationGoal extends Model { protected $fillable=['evaluation_id','title','description','due_date','status']; protected $casts=['due_date'=>'date']; public function evaluation(): BelongsTo{return $this->belongsTo(Evaluation::class);} }
