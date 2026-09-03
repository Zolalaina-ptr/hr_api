<?php
namespace App\Services;
use App\Models\{Employee,Attendance,Leave,Payroll,Contract};
class DashboardService {
 public function statistics(): array { $total=Employee::count();$active=Employee::where('status','active')->count();$today=Attendance::whereDate('date',today());$present=(clone $today)->whereIn('status',['present','remote'])->count();$pending=Leave::where('status','pending')->count();$mass=Payroll::where('period_month',now()->month)->where('period_year',now()->year)->sum('gross_pay'); return ['headcount'=>$total,'active_headcount'=>$active,'attendance_rate'=>$active?round($present/$active*100,2):0,'pending_leaves'=>$pending,'monthly_salary_mass'=>(float)$mass,'expiring_contracts'=>Contract::where('status','active')->whereBetween('end_date',[now(),now()->addDays(30)])->count()]; }
 public function demographics(): array {return ['by_gender'=>Employee::selectRaw('gender, count(*) as total')->groupBy('gender')->pluck('total','gender'),'by_contract_type'=>Employee::selectRaw('contract_type, count(*) as total')->groupBy('contract_type')->pluck('total','contract_type'),'by_status'=>Employee::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total','status')];}
}
