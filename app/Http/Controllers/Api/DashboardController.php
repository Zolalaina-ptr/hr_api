<?php
namespace App\Http\Controllers\Api;
use App\Services\DashboardService; use App\Traits\ApiResponseTrait;
class DashboardController {use ApiResponseTrait; public function __construct(private DashboardService $service){} public function statistics(){return $this->success($this->service->statistics());} public function headcount(){return $this->success(['total'=>\App\Models\Employee::count(),'active'=>\App\Models\Employee::where('status','active')->count()]);} public function attendance(){return $this->success(['rate'=>$this->service->statistics()['attendance_rate']]);} public function demographics(){return $this->success($this->service->demographics());}}
