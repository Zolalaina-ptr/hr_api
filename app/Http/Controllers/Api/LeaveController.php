<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\LeaveApprovalRequest;
use App\Http\Requests\Api\LeaveRequestRequest;
use App\Models\Leave;
use App\Models\LeaveType;
use App\Services\LeaveService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveController
{
    use ApiResponseTrait;

    public function __construct(private LeaveService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = [
            'employee_id' => $request->get('employee_id'),
            'leave_type_id' => $request->get('leave_type_id'),
            'status' => $request->get('status'),
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
            'department_id' => $request->get('department_id'),
            'per_page' => $request->get('per_page', 15),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== null);

        $leaves = $this->service->getLeavesPaginated($filters);

        return $this->success($leaves, 'Leaves retrieved successfully');
    }

    public function store(LeaveRequestRequest $request): JsonResponse
    {
        $leave = $this->service->createRequest($request->validated());

        return $this->success($leave, 'Leave request created successfully', 201);
    }

    public function show(Leave $leave): JsonResponse
    {
        $leave->load(['employee', 'leaveType', 'replacement', 'replacements', 'approver', 'histories']);

        return $this->success($leave, 'Leave retrieved successfully');
    }

    public function update(LeaveRequestRequest $request, Leave $leave): JsonResponse
    {
        $updated = $this->service->updateRequest($leave->id, $request->validated());

        return $this->success($updated, 'Leave updated successfully');
    }

    public function destroy(Leave $leave): JsonResponse
    {
        $cancelled = $this->service->cancelRequest($leave->id);

        return $this->success($cancelled, 'Leave cancelled successfully');
    }

    public function approve(LeaveApprovalRequest $request, Leave $leave): JsonResponse
    {
        $approved = $this->service->approveRequest($leave->id, $request->input('comment'));

        return $this->success($approved, 'Leave approved successfully');
    }

    public function reject(LeaveApprovalRequest $request, Leave $leave): JsonResponse
    {
        $rejected = $this->service->rejectRequest($leave->id, $request->input('comment'));

        return $this->success($rejected, 'Leave rejected successfully');
    }

    public function pending(Request $request): JsonResponse
    {
        $filters = [
            'department_id' => $request->get('department_id'),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== null);

        return $this->success($this->service->getPendingRequests($filters), 'Pending leaves retrieved successfully');
    }

    public function employee(int $employeeId, Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->get('status'),
            'year' => $request->get('year'),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== null);

        $leaves = $this->service->getLeavesByEmployee($employeeId, $filters);

        return $this->success($leaves, 'Employee leaves retrieved successfully');
    }

    public function balance(Request $request): JsonResponse
    {
        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $balances = $this->service->getBalance(
            (int) $request->input('employee_id'),
            $request->input('year') ? (int) $request->input('year') : null
        );

        return $this->success($balances, 'Leave balances retrieved successfully');
    }

    public function types(): JsonResponse
    {
        $types = LeaveType::where('is_active', true)->orderBy('name')->get();

        return $this->success($types, 'Leave types retrieved successfully');
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $filters = [
            'employee_id' => $request->get('employee_id'),
            'leave_type_id' => $request->get('leave_type_id'),
            'status' => $request->get('status'),
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== null);

        $format = $request->get('format', 'csv');

        return $this->service->exportLeaves($filters, $format);
    }

    public function statistics(Request $request): JsonResponse
    {
        $year = (int) ($request->get('year', now()->format('Y')));
        $stats = $this->service->getStatistics($year);

        return $this->success($stats, 'Leave statistics retrieved successfully');
    }

    public function autoApprove(Leave $leave): JsonResponse
    {
        $approved = $this->service->autoApprove($leave->id);

        return $this->success($approved, 'Leave auto-approved successfully');
    }
}