<?php
namespace App\Console\Commands;
use App\Jobs\GeneratePayrollJob; use App\Models\Employee; use Illuminate\Console\Command;
class GenerateMonthlyPayroll extends Command {protected $signature='payroll:generate {month?} {year?}'; protected $description='Queue payroll generation for active employees'; public function handle():int{$month=(int)($this->argument('month')?:now()->month);$year=(int)($this->argument('year')?:now()->year);Employee::where('status','active')->pluck('id')->each(fn($id)=>GeneratePayrollJob::dispatch($id,$month,$year));$this->info('Payroll jobs queued.');return self::SUCCESS;}}
