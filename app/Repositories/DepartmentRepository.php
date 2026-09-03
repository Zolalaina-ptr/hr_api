<?php

namespace App\Repositories;

use App\Models\Department;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Collection;

class DepartmentRepository
{
    /**
     * Get all departments
     */
    public function getAll(): Collection
    {
        return Department::with(['manager', 'parent', 'children', 'positions'])
            ->get();
    }

    /**
     * Get paginated departments
     */
    public function getPaginated(int $perPage = 15, array $filters = []): Paginator
    {
        $query = Department::with(['manager', 'parent', 'children', 'positions']);

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage)->through(fn ($p) => $p)->withQueryString();
    }

    /**
     * Find department by ID
     */
    public function findById(int $id): ?Department
    {
        return Department::with(['manager', 'parent', 'children', 'positions', 'employees', 'histories'])
            ->find($id);
    }

    /**
     * Find department by code
     */
    public function findByCode(string $code): ?Department
    {
        return Department::where('code', $code)->first();
    }

    /**
     * Create department
     */
    public function create(array $data): Department
    {
        return Department::create($data);
    }

    /**
     * Update department
     */
    public function update(Department $department, array $data): Department
    {
        $department->update($data);
        return $department->fresh(['manager', 'parent', 'children', 'positions']);
    }

    /**
     * Delete department
     */
    public function delete(Department $department): bool
    {
        return $department->delete();
    }

    /**
     * Get root departments (no parent)
     */
    public function getRootDepartments(): Collection
    {
        return Department::whereNull('parent_id')
            ->with(['children', 'manager', 'positions'])
            ->where('is_active', true)
            ->get();
    }

    /**
     * Get department hierarchy tree
     */
    public function getHierarchyTree(): array
    {
        return $this->getRootDepartments()
            ->map(fn ($dept) => $this->buildHierarchyTree($dept))
            ->values()
            ->toArray();
    }

    public function getChildren(int $parentId): Collection
    {
        return Department::with(['manager', 'positions', 'children'])
            ->where('parent_id', $parentId)
            ->get();
    }

    /**
     * Build recursive hierarchy tree
     */
    private function buildHierarchyTree(Department $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'description' => $department->description,
            'budget' => $department->budget,
            'is_active' => $department->is_active,
            'manager' => $department->manager ? [
                'id' => $department->manager->id,
                'first_name' => $department->manager->first_name,
                'last_name' => $department->manager->last_name,
            ] : null,
            'children' => $department->children->map(fn($child) => $this->buildHierarchyTree($child))->toArray(),
        ];
    }

    /**
     * Check if department exists
     */
    public function exists(int $id): bool
    {
        return Department::where('id', $id)->exists();
    }

    /**
     * Get active departments count
     */
    public function activeCount(): int
    {
        return Department::where('is_active', true)->count();
    }

    public function getDescendantIds(int $departmentId): array
    {
        $ids = [$departmentId];
        $stack = [$departmentId];

        while ($stack) {
            $current = array_pop($stack);
            $children = Department::where('parent_id', $current)->pluck('id')->all();
            foreach ($children as $childId) {
                $ids[] = $childId;
                $stack[] = $childId;
            }
        }

        return $ids;
    }

    public function getTotalBudgetFor(array $departmentIds): float
    {
        return (float) Department::whereIn('id', $departmentIds)->sum('budget');
    }
}
