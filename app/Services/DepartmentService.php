<?php

namespace App\Services;

use App\Models\Department;
use App\Models\DepartmentHistory;
use App\Models\Employee;
use App\Repositories\DepartmentRepository;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;

class DepartmentService
{
    public function __construct(private DepartmentRepository $repository)
    {
    }

    /**
     * Get all departments
     */
    public function getAll(): Collection
    {
        return $this->repository->getAll();
    }

    /**
     * Get paginated departments
     */
    public function getPaginated(int $perPage = 15, array $filters = []): Paginator
    {
        return $this->repository->getPaginated($perPage, $filters);
    }

    /**
     * Get department by ID
     */
    public function getById(int $id): ?Department
    {
        return $this->repository->findById($id);
    }

    /**
     * Get department by code
     */
    public function getByCode(string $code): ?Department
    {
        return $this->repository->findByCode($code);
    }

    /**
     * Create department
     */
    public function create(array $data): Department
    {
        return $this->repository->create($data);
    }

    /**
     * Update department
     */
    public function update(Department $department, array $data): Department
    {
        return $this->repository->update($department, $data);
    }

    /**
     * Delete department
     */
    public function delete(Department $department): bool
    {
        return $this->repository->delete($department);
    }

    /**
     * Assign manager to department
     */
    public function assignManager(Department $department, ?int $managerId): Department
    {
        if ($managerId === null) {
            $department->manager_id = null;
            $department->save();

            return $department->refresh();
        }

        if ($managerId !== null && ! Employee::whereKey($managerId)->exists()) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
        }

        if ($department->manager_id !== $managerId) {
            DepartmentHistory::create([
                'department_id' => $department->id,
                'effective_date' => now()->format('Y-m-d'),
                'previous_manager_id' => $department->manager_id,
                'new_manager_id' => $managerId,
                'change_reason' => 'Manager assignment',
                'changed_by' => auth()->id(),
            ]);
        }

        $department->manager_id = $managerId;
        $department->save();

        return $department->refresh();
    }

    /**
     * Get department hierarchy
     */
    public function getHierarchy(): array
    {
        return $this->repository->getHierarchyTree();
    }

    /**
     * Get budget overview for department and its children
     */
    public function getBudgetOverview(Department $department): array
    {
        $descendantIds = $this->repository->getDescendantIds($department->id);

        $children = $department->children->map(fn ($child) => $this->getHierarchyWithBudget($child))->toArray();
        $subtotal = ($department->budget ?? 0) + array_sum(array_map(fn ($c) => $c['subtotal_budget'], $children));

        return [
            'department_id' => $department->id,
            'allocated_budget' => (float) ($department->budget ?? 0),
            'total_budget' => (float) $this->repository->getTotalBudgetFor($descendantIds),
            'subtotal_budget' => $subtotal,
            'departments' => $children,
        ];
    }

    /**
     * Get hierarchy with budget information
     */
    private function getHierarchyWithBudget(Department $department): array
    {
        $children = $department->children->map(fn($child) => $this->getHierarchyWithBudget($child))->toArray();
        
        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'budget' => $department->budget ?? 0,
            'is_active' => $department->is_active,
            'children' => $children,
            'subtotal_budget' => ($department->budget ?? 0) + array_sum(array_map(fn($c) => $c['subtotal_budget'], $children)),
        ];
    }

    /**
     * Calculate total budget recursively
     */
    private function calculateTotalBudget(array $hierarchy): float
    {
        $total = $hierarchy['budget'] ?? 0;
        
        foreach ($hierarchy['children'] ?? [] as $child) {
            $total += $this->calculateTotalBudget($child);
        }
        
        return $total;
    }

    /**
     * Get department headcount (employee count)
     */
    public function getHeadcount(Department $department, bool $includeChildren = true): int
    {
        $descendantIds = $includeChildren
            ? $this->repository->getDescendantIds($department->id)
            : [$department->id];

        return (int) \App\Models\Employee::whereIn('department_id', $descendantIds)->count();
    }

    /**
     * Get headcount overview for department
     */
    public function getHeadcountOverview(Department $department): array
    {
        $descendantIds = $this->repository->getDescendantIds($department->id);

        $directHeadcount = $department->employees()->count();
        $totalHeadcount = (int) \App\Models\Employee::whereIn('department_id', $descendantIds)->count();
        $activeEmployees = (int) \App\Models\Employee::whereIn('department_id', $descendantIds)->where('status', 'active')->count();
        $inactiveEmployees = (int) \App\Models\Employee::whereIn('department_id', $descendantIds)->where('status', 'inactive')->count();

        $byStatus = \App\Models\Employee::whereIn('department_id', $descendantIds)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return [
            'total_headcount' => $totalHeadcount,
            'direct_headcount' => $directHeadcount,
            'active_employees' => $activeEmployees,
            'inactive_employees' => $inactiveEmployees,
            'by_status' => $byStatus,
        ];
    }

    /**
     * Get department statistics
     */
    public function getStatistics(Department $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'headcount' => $this->getHeadcount($department, false),
            'total_headcount' => $this->getHeadcount($department, true),
            'budget' => $department->budget ?? 0,
            'positions_count' => $department->positions()->count(),
            'child_departments_count' => $department->children()->count(),
            'is_active' => $department->is_active,
        ];
    }

    /**
     * Check if department can be deleted
     */
    public function canDelete(Department $department): array
    {
        $issues = [];

        if ($department->employees()->where('status', 'active')->exists()) {
            $issues[] = 'Department has active employees';
        }

        if ($department->children()->exists()) {
            $issues[] = 'Department has child departments';
        }

        return [
            'can_delete' => empty($issues),
            'issues' => $issues,
        ];
    }
}
