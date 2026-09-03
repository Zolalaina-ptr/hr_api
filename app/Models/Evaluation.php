<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class Evaluation extends Model {
 protected $fillable=['employee_id','evaluator_id','evaluation_date','period_start','period_end','skills_score','soft_skills_score','management_score','autonomy_score','results_score','overall_score','comments','goals','strengths','areas_for_improvement','status','feedback','validated_at','next_evaluation_date'];
 protected $casts=['evaluation_date'=>'date','period_start'=>'date','period_end'=>'date','next_evaluation_date'=>'date','validated_at'=>'datetime'];
 public function employee(): BelongsTo{return $this->belongsTo(Employee::class);}
 public function evaluator(): BelongsTo{return $this->belongsTo(User::class,'evaluator_id');}
 public function goals(): HasMany{return $this->hasMany(EvaluationGoal::class);}
}
