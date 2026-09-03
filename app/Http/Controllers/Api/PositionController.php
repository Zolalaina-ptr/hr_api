<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\StorePositionRequest;
use App\Http\Requests\Api\UpdatePositionRequest;
use App\Models\Position;
use App\Services\PositionService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PositionController
{
    use ApiResponseTrait;

    public function __construct(private PositionService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'department_id' => $request->get('department_id'),
            'level' => $request->get('level'),
            'search' => $request->get('search'),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : null,
        ];
        $filters = array_filter($filters, fn ($v) => $v !== null);

        $positions = $this->service->getPaginated($request->get('per_page', 15), $filters);

        return $this->success($positions, 'Positions retrieved successfully');
    }

    public function store(StorePositionRequest $request): JsonResponse
    {
        $position = $this->service->create($request->validated());

        return $this->success($position, 'Position created successfully', 201);
    }

    public function show(Position $position): JsonResponse
    {
        $position = $this->service->getById($position->id);

        if (! $position) {
            return $this->notFound('Position not found');
        }

        return $this->success($position, 'Position retrieved successfully');
    }

    public function update(UpdatePositionRequest $request, Position $position): JsonResponse
    {
        $updated = $this->service->update($position, $request->validated());

        return $this->success($updated, 'Position updated successfully');
    }

    public function destroy(Position $position): JsonResponse
    {
        $check = $this->service->canDelete($position);

        if (! $check['can_delete']) {
            return $this->error(
                'Cannot delete position: '.implode(', ', $check['issues']),
                422
            );
        }

        $this->service->delete($position);

        return $this->success(message: 'Position deleted successfully');
    }

    public function assignToDepartment(Request $request, Position $position): JsonResponse
    {
        $request->validate([
            'department_id' => 'required|exists:departments,id',
        ]);

        $updated = $this->service->assignToDepartment($position, (int) $request->get('department_id'));

        return $this->success($updated, 'Position assigned to department successfully');
    }

    public function salaryRange(Request $request): JsonResponse
    {
        $request->validate([
            'min_salary' => 'required|numeric|min:0',
            'max_salary' => 'required|numeric|min:0|gte:min_salary',
        ]);

        $positions = $this->service->getPositionsBySalaryRange(
            (float) $request->get('min_salary'),
            (float) $request->get('max_salary')
        );

        return $this->success($positions, 'Positions retrieved successfully by salary range');
    }

    public function levelStats(Request $request, string $level): JsonResponse
    {
        if (! in_array($level, ['junior', 'mid', 'senior', 'lead', 'manager', 'director'], true)) {
            return $this->error('Invalid level', 422);
        }

        $range = $this->service->getSalaryRange($level);

        return $this->success($range, 'Position salary range retrieved successfully');
    }

    public function allSalaryRanges(): JsonResponse
    {
        return $this->success($this->service->getAllSalaryRanges(), 'All salary ranges retrieved successfully');
    }

    public function available(Request $request): JsonResponse
    {
        $departmentId = $request->get('department_id');
        $positions = $this->service->getAvailablePositions($departmentId ? (int) $departmentId : null);

        return $this->success($positions, 'Available positions retrieved successfully');
    }

    public function requirements(Position $position): JsonResponse
    {
        $requirements = $position->requirements()->with('position')->get();

        return $this->success($requirements, 'Position requirements retrieved successfully');
    }

    public function employees(Position $position, Request $request): JsonResponse
    {
        $employees = $position->employees()
            ->with('department')
            ->when($request->has('status'), fn ($q) => $q->where('status', $request->status))
            ->paginate($request->get('per_page', 15));

        return $this->success($employees, 'Position employees retrieved successfully');
    }

    public function statistics(Position $position): JsonResponse
    {
        return $this->success($this->service->getStatistics($position), 'Position statistics retrieved successfully');
    }

    public function marketComparison(Position $position): JsonResponse
    {
        return $this->success($this->service->getMarketComparison($position), 'Position market comparison retrieved successfully');
    }
}