<?php
namespace App\Http\Controllers\Api;
use App\Http\Requests\Api\EvaluationRequest;
use App\Models\Evaluation;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
class EvaluationController { use ApiResponseTrait;
 public function index(Request $r){$q=Evaluation::with(['employee','evaluator'])->when($r->employee_id,fn($q,$v)=>$q->where('employee_id',$v))->when($r->status,fn($q,$v)=>$q->where('status',$v))->latest('evaluation_date');return $this->success($q->paginate(min((int)$r->get('per_page',15),100)));}
 public function store(EvaluationRequest $r){$e=Evaluation::create($r->validated()+['evaluator_id'=>$r->user()->id]);$this->score($e);return $this->success($e->load(['employee','evaluator']), 'Evaluation created successfully',201);}
 public function show(Evaluation $evaluation){return $this->success($evaluation->load(['employee','evaluator','goals']));}
 public function update(EvaluationRequest $r, Evaluation $evaluation){$evaluation->update($r->validated());$this->score($evaluation);return $this->success($evaluation->fresh());}
 public function destroy(Evaluation $evaluation){$evaluation->delete();return $this->success(null,'Evaluation deleted successfully');}
 public function employee(int $employee){return $this->success(Evaluation::where('employee_id',$employee)->with('evaluator')->latest()->paginate(15));}
 public function upcoming(){return $this->success(Evaluation::whereNotNull('next_evaluation_date')->whereBetween('next_evaluation_date',[now(),now()->addDays(90)])->with('employee')->get());}
 public function validateEvaluation(Evaluation $evaluation){$evaluation->update(['status'=>'completed','validated_at'=>now()]);return $this->success($evaluation->fresh(),'Evaluation validated successfully');}
 private function score(Evaluation $e):void{$values=collect(['skills_score','soft_skills_score','management_score','autonomy_score','results_score'])->map(fn($k)=>$e->{$k})->filter(fn($v)=>$v!==null);if($values->isNotEmpty())$e->update(['overall_score'=>round($values->avg(),2)]);}
}
