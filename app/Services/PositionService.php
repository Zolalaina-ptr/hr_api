<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Position;
use App\Repositories\PositionRepository;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;

class PositionService
{
    public function __construct(private PositionRepository $repository)
    {
    }

    /**
     * Get all positions
     */
    public function getAll(): Collection
    {
        return $this->repository->getAll();
    }

    /**
     * Get paginated positions
     */
    public function getPaginated(int $perPage = 15, array $filters = []): Paginator
    {
        return $this->repository->getPaginated($perPage, $filters);
    }

    /**
     * Get position by ID
     */
    public function getById(int $id): ?Position
    {
        return $this->repository->findById($id);
    }

    /**
     * Get position by code
     */
    public function getByCode(string $code): ?Position
    {
        return $this->repository->findByCode($code);
    }

    /**
     * Create position
     */
    public function create(array $data): Position
    {
        return $this->repository->create($data);
    }

    /**
     * Update position
     */
    public function update(Position $position, array $data): Position
    {
        return $this->repository->update($position, $data);
    }

    /**
     * Delete position
     */
    public function delete(Position $position): bool
    {
        return $this->repository->delete($position);
    }

    /**
     * Assign position to department
     */
    public function assignToDepartment(Position $position, int $departmentId): Position
    {
        $department = Department::findOrFail($departmentId);
        
        $position->department_id = $departmentId;
        $position->save();

        return $position->fresh(['department']);
    }

    /**
     * Move position to another department
     */
    public function moveToDepartment(Position $position, int $newDepartmentId): Position
    {
        return $this->assignToDepartment($position, $newDepartmentId);
    }

    /**
     * Get salary range for position level
     */
    public function getSalaryRange(string $level): array
    {
        $positions = $this->repository->getByLevel($level);

        if ($positions->isEmpty()) {
            return [
                'level' => $level,
                'count' => 0,
                'min_salary' => 0,
                'max_salary' => 0,
                'average_min_salary' => 0,
                'average_max_salary' => 0,
                'average_salary' => 0,
            ];
        }

        $minSalaries = $positions->pluck('min_salary')->map(fn ($v) => (float) $v);
        $maxSalaries = $positions->pluck('max_salary')->map(fn ($v) => (float) $v);

        return [
            'level' => $level,
            'count' => $positions->count(),
            'min_salary' => $minSalaries->min(),
            'max_salary' => $maxSalaries->max(),
            'average_min_salary' => $minSalaries->average(),
            'average_max_salary' => $maxSalaries->average(),
            'average_salary' => ($minSalaries->average() + $maxSalaries->average()) / 2,
        ];
    }

    /**
     * Get positions in salary range
     */
    public function getPositionsBySalaryRange(float $minSalary, float $maxSalary): Collection
    {
        return $this->repository->getBySalaryRange($minSalary, $maxSalary);
    }

    /**
     * Get available positions (positions without assigned employees or with open slots)
     */
    public function getAvailablePositions(?int $departmentId = null): Collection
    {
        $positions = $this->repository->getAvailable();

        if ($departmentId) {
            $positions = $positions->filter(fn ($p) => $p->department_id === $departmentId)->values();
        }

        return $positions;
    }

    /**
     * Get unassigned positions
     */
    public function getUnassignedPositions(): Collection
    {
        return Position::whereDoesntHave('employees')
            ->with(['department', 'requirements'])
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get positions by department
     */
    public function getByDepartment(int $departmentId): Collection
    {
        return $this->repository->getByDepartment($departmentId);
    }

    /**
     * Get positions by level
     */
    public function getByLevel(string $level): Collection
    {
        return $this->repository->getByLevel($level);
    }

    /**
     * Get salary ranges for all levels
     */
    public function getAllSalaryRanges(): array
    {
        return array_map(
            fn ($level) => $this->getSalaryRange($level),
            $this->repository->getLevels()
        );
    }

    /**
     * Get position statistics
     */
    public function getStatistics(Position $position): array
    {
        $employees = $position->employees;

        return [
            'id' => $position->id,
            'title' => $position->title,
            'code' => $position->code,
            'level' => $position->level,
            'department' => $position->department->name ?? 'N/A',
            'salary_range' => [
                'min' => $position->min_salary,
                'max' => $position->max_salary,
            ],
            'assigned_employees' => $employees->count(),
            'employee_names' => $employees->map(fn($e) => "{$e->first_name} {$e->last_name}")->toArray(),
            'requirements_count' => $position->requirements()->count(),
            'is_active' => $position->is_active,
        ];
    }

    /**
     * Check if position can be deleted
     */
    public function canDelete(Position $position): array
    {
        $issues = [];

        if ($position->employees()->exists()) {
            $employeeCount = $position->employees()->count();
            $issues[] = "Position has {$employeeCount} assigned employee(s)";
        }

        return [
            'can_delete' => empty($issues),
            'issues' => $issues,
        ];
    }

    /**
     * Get market comparison
     */
    public function getMarketComparison(Position $position): array
    {
        $levelStats = $this->getSalaryRange($position->level);
        $departmentPositions = $this->repository->getByDepartment($position->department_id);
        
        $departmentAvgMin = $departmentPositions->avg('min_salary') ?? 0;
        $departmentAvgMax = $departmentPositions->avg('max_salary') ?? 0;

        return [
            'position' => [
                'title' => $position->title,
                'min_salary' => $position->min_salary,
                'max_salary' => $position->max_salary,
            ],
            'level_average' => [
                'min_salary' => $levelStats['average_min_salary'],
                'max_salary' => $levelStats['average_max_salary'],
            ],
            'department_average' => [
                'min_salary' => $departmentAvgMin,
                'max_salary' => $departmentAvgMax,
            ],
            'position_vs_level' => [
                'min_variance' => $position->min_salary - $levelStats['average_min_salary'],
                'max_variance' => $position->max_salary - $levelStats['average_max_salary'],
            ],
        ];
    }
}
