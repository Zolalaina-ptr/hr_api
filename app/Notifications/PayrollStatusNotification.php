<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable; use Illuminate\Contracts\Queue\ShouldQueue; use Illuminate\Notifications\Messages\DatabaseMessage; use Illuminate\Notifications\Notification;
class PayrollStatusNotification extends Notification implements ShouldQueue {use Queueable; public function __construct(public int $payrollId,public string $status,public float $netPay){} public function via($notifiable):array{return ['database'];} public function toDatabase($notifiable):array{return ['category'=>'payroll','priority'=>'medium','payroll_id'=>$this->payrollId,'status'=>$this->status,'net_pay'=>$this->netPay,'message'=>'Votre paie est '.$this->status.'.'];}}
