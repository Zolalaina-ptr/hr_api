<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StoreEmployeeRequest;
use App\Http\Requests\Api\UpdateEmployeeRequest;
use App\Models\Employee;
use App\Models\Position;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController
{
    use ApiResponseTrait;

    /**
     * Display a listing of the resource (paginated, filterable, searchable).
     */
    public function index(Request $request): JsonResponse
    {
        $employees = Employee::query()
            ->with(['department', 'position'])
            ->when($request->get('status'), fn ($query, $status) => $query->where('status', $status))
            ->when($request->get('department_id'), fn ($query, $id) => $query->where('department_id', $id))
            ->when($request->get('position_id'), fn ($query, $id) => $query->where('position_id', $id))
            ->when($request->get('contract_type'), fn ($query, $type) => $query->where('contract_type', $type))
            ->when($request->get('search'), function ($query, $term) {
                $term = mb_strtolower((string) $term);
                $query->where(function ($sub) use ($term) {
                    $sub->whereRaw('LOWER(first_name) LIKE ?', ["%{$term}%"])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', ["%{$term}%"])
                        ->orWhereRaw('LOWER(email) LIKE ?', ["%{$term}%"])
                        ->orWhereRaw('LOWER(registration_number) LIKE ?', ["%{$term}%"]);
                });
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate((int) $request->get('per_page', 15));

        return $this->success(
            data: $employees,
            message: 'Employees retrieved successfully'
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = Employee::create($request->validated());

        return $this->success(
            data: $employee->load(['department', 'position']),
            message: 'Employee created successfully',
            status: 201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee): JsonResponse
    {
        return $this->success(
            data: $employee->load(['department', 'position', 'manager']),
            message: 'Employee retrieved successfully'
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $data = $request->validated();
        $changeReason = $data['change_reason'] ?? null;
        unset($data['change_reason']);

        // An employee cannot be their own manager
        if (array_key_exists('manager_id', $data) && (int) $data['manager_id'] === (int) $employee->id) {
            return $this->validationError([
                'manager_id' => ['An employee cannot be their own manager.'],
            ]);
        }

        $positionChanged = array_key_exists('position_id', $data)
            && $data['position_id'] !== $employee->position_id;
        $salaryChanged = array_key_exists('base_salary', $data)
            && (float) ($data['base_salary'] ?? 0) !== (float) $employee->base_salary;

        if ($positionChanged || $salaryChanged) {
            $newPositionId = $data['position_id'] ?? $employee->position_id;

            $employee->histories()->create([
                'effective_date' => now()->toDateString(),
                'previous_position' => $employee->position?->title,
                'new_position' => $newPositionId
                    ? Position::find($newPositionId)?->title
                    : null,
                'previous_salary' => $employee->base_salary,
                'new_salary' => $data['base_salary'] ?? $employee->base_salary,
                'change_reason' => $changeReason,
                'changed_by' => $request->user()?->id,
            ]);
        }

        $employee->update($data);

        return $this->success(
            data: $employee->fresh()->load(['department', 'position']),
            message: 'Employee updated successfully'
        );
    }

    /**
     * Remove the specified resource from storage (soft delete).
     */
    public function destroy(Employee $employee): JsonResponse
    {
        $employee->delete();

        return $this->success(
            message: 'Employee deleted successfully'
        );
    }

    /**
     * Display the change history of the specified resource.
     */
    public function history(Employee $employee): JsonResponse
    {
        $histories = $employee->histories()
            ->with('changedBy')
            ->latest('effective_date')
            ->paginate(15);

        return $this->success(
            data: $histories,
            message: 'Employee history retrieved successfully'
        );
    }
}
