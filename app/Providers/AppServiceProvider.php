<?php

namespace App\Providers;

use App\Repositories\AttendanceRepository;
use App\Repositories\DepartmentRepository;
use App\Repositories\LeaveBalanceRepository;
use App\Repositories\LeaveRepository;
use App\Repositories\PositionRepository;
use App\Services\AttendanceService;
use App\Services\DepartmentService;
use App\Services\LeaveService;
use App\Services\PositionService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(DepartmentRepository::class);
        $this->app->singleton(PositionRepository::class);
        $this->app->singleton(AttendanceRepository::class);
        $this->app->singleton(LeaveRepository::class);
        $this->app->singleton(LeaveBalanceRepository::class);

        $this->app->singleton(DepartmentService::class, function ($app) {
            return new DepartmentService($app->make(DepartmentRepository::class));
        });

        $this->app->singleton(PositionService::class, function ($app) {
            return new PositionService($app->make(PositionRepository::class));
        });

        $this->app->singleton(AttendanceService::class, function ($app) {
            return new AttendanceService($app->make(AttendanceRepository::class));
        });

        $this->app->singleton(LeaveService::class, function ($app) {
            return new LeaveService(
                $app->make(LeaveRepository::class),
                $app->make(LeaveBalanceRepository::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
    }
}
