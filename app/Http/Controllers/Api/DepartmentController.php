<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreDepartmentRequest;
use App\Http\Requests\Api\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\DepartmentService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController
{
    use ApiResponseTrait;

    public function __construct(private DepartmentService $service)
    {
    }

    /**
     * Display a listing of departments
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
            'parent_id' => $request->get('parent_id'),
            'search' => $request->get('search'),
        ];

        $filters = array_filter($filters, fn($v) => $v !== null);

        $departments = $this->service->getPaginated($request->get('per_page', 15), $filters);

        return $this->success(
            data: $departments,
            message: 'Departments retrieved successfully'
        );
    }

    /**
     * Store a newly created department
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = $this->service->create($request->validated());

        return $this->success(
            data: $department,
            message: 'Department created successfully',
            status: 201
        );
    }

    /**
     * Display the specified department
     */
    public function show(Department $department): JsonResponse
    {
        $dept = $this->service->getById($department->id);

        if (!$dept) {
            return $this->error(
                message: 'Department not found',
                status: 404
            );
        }

        return $this->success(
            data: $dept,
            message: 'Department retrieved successfully'
        );
    }

    /**
     * Update the specified department
     */
    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $updated = $this->service->update($department, $request->validated());

        return $this->success(
            data: $updated,
            message: 'Department updated successfully'
        );
    }

    /**
     * Delete the specified department
     */
    public function destroy(Department $department): JsonResponse
    {
        $check = $this->service->canDelete($department);

        if (!$check['can_delete']) {
            return $this->error(
                message: 'Cannot delete department: ' . implode(', ', $check['issues']),
                status: 422
            );
        }

        $this->service->delete($department);

        return $this->success(
            message: 'Department deleted successfully'
        );
    }

    /**
     * Get department hierarchy
     */
    public function hierarchy(Request $request): JsonResponse
    {
        $hierarchy = $this->service->getHierarchy();

        return $this->success(
            data: $hierarchy,
            message: 'Department hierarchy retrieved successfully'
        );
    }

    /**
     * Get employees in a department
     */
    public function employees(Department $department, Request $request): JsonResponse
    {
        $employees = $department->employees()
            ->with('position')
            ->when($request->has('status'), function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->paginate($request->get('per_page', 15));

        return $this->success(
            data: $employees,
            message: 'Department employees retrieved successfully'
        );
    }

    /**
     * Get department budget overview
     */
    public function budgetOverview(Department $department): JsonResponse
    {
        $overview = $this->service->getBudgetOverview($department);

        return $this->success(
            data: $overview,
            message: 'Department budget overview retrieved successfully'
        );
    }

    /**
     * Get department headcount
     */
    public function headcount(Department $department, Request $request): JsonResponse
    {
        $headcount = $request->boolean('include_children', true)
            ? $this->service->getHeadcount($department, true)
            : $this->service->getHeadcount($department, false);

        $overview = $this->service->getHeadcountOverview($department);

        return $this->success(
            data: [
                'total_headcount' => $headcount,
                'overview' => $overview,
            ],
            message: 'Department headcount retrieved successfully'
        );
    }

    /**
     * Assign manager to department
     */
    public function assignManager(Department $department, Request $request): JsonResponse
    {
        $request->validate([
            'manager_id' => 'nullable|exists:employees,id',
        ]);

        $updated = $this->service->assignManager($department, $request->get('manager_id'));

        return $this->success(
            data: $updated,
            message: 'Manager assigned successfully'
        );
    }

    /**
     * Get department statistics
     */
    public function statistics(Department $department): JsonResponse
    {
        $stats = $this->service->getStatistics($department);

        return $this->success(
            data: $stats,
            message: 'Department statistics retrieved successfully'
        );
    }

    /**
     * Format department hierarchy recursively
     */
    private function formatHierarchy(Department $department): array
    {
        return [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'budget' => $department->budget,
            'is_active' => $department->is_active,
            'manager' => $department->manager ? [
                'id' => $department->manager->id,
                'first_name' => $department->manager->first_name,
                'last_name' => $department->manager->last_name,
            ] : null,
            'children' => $department->children->map(function ($child) {
                return $this->formatHierarchy($child);
            })->toArray(),
        ];
    }
}
