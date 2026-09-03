<?php

use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\LeaveController;
use App\Http\Controllers\Api\PositionController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', function () {
            return response()->json(['success' => true, 'message' => 'Bienvenue admin.']);
        });
    });

    Route::middleware('permission:view users')->group(function () {
        Route::get('/users', function () {
            return response()->json(['success' => true, 'data' => []]);
        });
    });

    // Phase 4: Departments and Positions Routes
    Route::middleware('permission:view departments|manage departments')->group(function () {
        Route::get('/departments', [DepartmentController::class, 'index']);
        Route::get('/departments/hierarchy', [DepartmentController::class, 'hierarchy']);
        Route::get('/departments/{department}', [DepartmentController::class, 'show']);
        Route::get('/departments/{department}/employees', [DepartmentController::class, 'employees']);
        Route::get('/departments/{department}/headcount', [DepartmentController::class, 'headcount']);
        Route::get('/departments/{department}/budget-overview', [DepartmentController::class, 'budgetOverview']);
        Route::get('/departments/{department}/statistics', [DepartmentController::class, 'statistics']);
    });

    Route::middleware('permission:create departments')->group(function () {
        Route::post('/departments', [DepartmentController::class, 'store']);
    });

    Route::middleware('permission:update departments|manage departments')->group(function () {
        Route::put('/departments/{department}', [DepartmentController::class, 'update']);
        Route::patch('/departments/{department}/manager', [DepartmentController::class, 'assignManager']);
    });

    Route::middleware('permission:delete departments')->group(function () {
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy']);
    });

    Route::middleware('permission:view positions|manage positions')->group(function () {
        Route::get('/positions', [PositionController::class, 'index']);
        Route::get('/positions/available', [PositionController::class, 'available']);
        Route::get('/positions/salary-range', [PositionController::class, 'salaryRange']);
        Route::get('/positions/salary-ranges', [PositionController::class, 'allSalaryRanges']);
        Route::get('/positions/level/{level}', [PositionController::class, 'levelStats']);
        Route::get('/positions/{position}', [PositionController::class, 'show']);
        Route::get('/positions/{position}/requirements', [PositionController::class, 'requirements']);
        Route::get('/positions/{position}/employees', [PositionController::class, 'employees']);
        Route::get('/positions/{position}/statistics', [PositionController::class, 'statistics']);
        Route::get('/positions/{position}/market-comparison', [PositionController::class, 'marketComparison']);
    });

    Route::middleware('permission:create positions')->group(function () {
        Route::post('/positions', [PositionController::class, 'store']);
    });

    Route::middleware('permission:update positions|manage positions')->group(function () {
        Route::put('/positions/{position}', [PositionController::class, 'update']);
        Route::patch('/positions/{position}/department', [PositionController::class, 'assignToDepartment']);
    });

    Route::middleware('permission:delete positions')->group(function () {
        Route::delete('/positions/{position}', [PositionController::class, 'destroy']);
    });

    // Phase 5: Attendance routes
    Route::middleware('permission:view attendance|manage attendance')->group(function () {
        Route::get('/attendances', [AttendanceController::class, 'index']);
        Route::get('/attendances/today', [AttendanceController::class, 'today']);
        Route::get('/attendances/monthly', [AttendanceController::class, 'monthly']);
        Route::get('/attendances/export', [AttendanceController::class, 'export']);
        Route::get('/attendances/statistics', [AttendanceController::class, 'statistics']);
        Route::get('/attendances/employee/{employee}', [AttendanceController::class, 'employee']);
        Route::get('/attendances/{attendance}', [AttendanceController::class, 'show']);
    });

    Route::middleware('permission:create attendance')->group(function () {
        Route::post('/attendances/clock-in', [AttendanceController::class, 'clockIn']);
    });

    Route::middleware('permission:update attendance|manage attendance')->group(function () {
        Route::patch('/attendances/{attendance}/clock-out', [AttendanceController::class, 'clockOut']);
        Route::patch('/attendances/{attendance}/approve', [AttendanceController::class, 'approve']);
        Route::patch('/attendances/{attendance}/reject', [AttendanceController::class, 'reject']);
        Route::put('/attendances/{attendance}', [AttendanceController::class, 'update']);
        Route::post('/attendances/bulk', [AttendanceController::class, 'bulk']);
    });

    Route::middleware('permission:delete attendance')->group(function () {
        Route::delete('/attendances/{attendance}', [AttendanceController::class, 'destroy']);
    });

    // Phase 10: Dashboard and reporting
    Route::prefix('dashboard')->group(function () {
        Route::get('/statistics', [\App\Http\Controllers\Api\DashboardController::class, 'statistics']);
        Route::get('/headcount', [\App\Http\Controllers\Api\DashboardController::class, 'headcount']);
        Route::get('/attendance', [\App\Http\Controllers\Api\DashboardController::class, 'attendance']);
        Route::get('/demographics', [\App\Http\Controllers\Api\DashboardController::class, 'demographics']);
    });

    // Phase 9: Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\NotificationController::class, 'index']);
        Route::get('/unread', [\App\Http\Controllers\Api\NotificationController::class, 'unread']);
        Route::patch('/read-all', [\App\Http\Controllers\Api\NotificationController::class, 'markAllRead']);
        Route::patch('/{notification}/read', [\App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
        Route::delete('/{notification}', [\App\Http\Controllers\Api\NotificationController::class, 'destroy']);
    });

    // Phase 8: Contracts and payroll routes
    Route::middleware('permission:view contracts|manage contracts')->group(function () { Route::get('/contracts',[\App\Http\Controllers\Api\ContractController::class,'index']); Route::get('/contracts/expiring',[\App\Http\Controllers\Api\ContractController::class,'expiring']); Route::get('/contracts/employee/{employee}',[\App\Http\Controllers\Api\ContractController::class,'employee']); Route::get('/contracts/{contract}',[\App\Http\Controllers\Api\ContractController::class,'show']); });
    Route::middleware('permission:create contracts')->post('/contracts',[\App\Http\Controllers\Api\ContractController::class,'store']);
    Route::middleware('permission:update contracts|manage contracts')->group(function(){Route::put('/contracts/{contract}',[\App\Http\Controllers\Api\ContractController::class,'update']);Route::delete('/contracts/{contract}',[\App\Http\Controllers\Api\ContractController::class,'destroy']);});
    Route::middleware('permission:view payroll|manage payroll')->group(function(){Route::get('/payrolls',[\App\Http\Controllers\Api\PayrollController::class,'index']);Route::get('/payrolls/export',[\App\Http\Controllers\Api\PayrollController::class,'export']);Route::get('/payrolls/employee/{employee}',[\App\Http\Controllers\Api\PayrollController::class,'employee']);Route::get('/payrolls/{payroll}',[\App\Http\Controllers\Api\PayrollController::class,'show']);});
    Route::middleware('permission:create payroll')->post('/payrolls',[\App\Http\Controllers\Api\PayrollController::class,'store']);
    Route::middleware('permission:update payroll|manage payroll')->group(function(){Route::patch('/payrolls/{payroll}/validate',[\App\Http\Controllers\Api\PayrollController::class,'validatePayroll']);Route::patch('/payrolls/{payroll}/pay',[\App\Http\Controllers\Api\PayrollController::class,'pay']);});

    // Phase 7: Evaluations routes
    Route::middleware('permission:view evaluations|manage evaluations')->group(function () {
        Route::get('/evaluations', [\App\Http\Controllers\Api\EvaluationController::class, 'index']);
        Route::get('/evaluations/upcoming', [\App\Http\Controllers\Api\EvaluationController::class, 'upcoming']);
        Route::get('/evaluations/employee/{employee}', [\App\Http\Controllers\Api\EvaluationController::class, 'employee']);
        Route::get('/evaluations/{evaluation}', [\App\Http\Controllers\Api\EvaluationController::class, 'show']);
    });
    Route::middleware('permission:create evaluations')->post('/evaluations', [\App\Http\Controllers\Api\EvaluationController::class, 'store']);
    Route::middleware('permission:update evaluations|manage evaluations')->group(function () {
        Route::put('/evaluations/{evaluation}', [\App\Http\Controllers\Api\EvaluationController::class, 'update']);
        Route::patch('/evaluations/{evaluation}/validate', [\App\Http\Controllers\Api\EvaluationController::class, 'validateEvaluation']);
    });
    Route::middleware('permission:delete evaluations')->delete('/evaluations/{evaluation}', [\App\Http\Controllers\Api\EvaluationController::class, 'destroy']);

    // Phase 6: Leave routes
    Route::middleware('permission:view leaves|manage leaves')->group(function () {
        Route::get('/leaves', [LeaveController::class, 'index']);
        Route::get('/leaves/pending', [LeaveController::class, 'pending']);
        Route::get('/leaves/types', [LeaveController::class, 'types']);
        Route::get('/leaves/balance', [LeaveController::class, 'balance']);
        Route::get('/leaves/statistics', [LeaveController::class, 'statistics']);
        Route::get('/leaves/export', [LeaveController::class, 'export']);
        Route::get('/leaves/employee/{employee}', [LeaveController::class, 'employee']);
        Route::get('/leaves/{leave}', [LeaveController::class, 'show']);
    });

    Route::middleware('permission:create leaves')->group(function () {
        Route::post('/leaves', [LeaveController::class, 'store']);
    });

    Route::middleware('permission:update leaves|manage leaves')->group(function () {
        Route::put('/leaves/{leave}', [LeaveController::class, 'update']);
        Route::patch('/leaves/{leave}/approve', [LeaveController::class, 'approve']);
        Route::patch('/leaves/{leave}/reject', [LeaveController::class, 'reject']);
        Route::patch('/leaves/{leave}/auto-approve', [LeaveController::class, 'autoApprove']);
    });

    Route::middleware('permission:delete leaves|manage leaves')->group(function () {
        Route::delete('/leaves/{leave}', [LeaveController::class, 'destroy']);
    });
});