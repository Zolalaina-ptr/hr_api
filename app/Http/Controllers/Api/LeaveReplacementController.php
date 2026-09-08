<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\LeaveReplacementRequest;
use App\Models\Leave;
use App\Models\LeaveReplacement;
use App\Services\LeaveReplacementService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveReplacementController
{
    use ApiResponseTrait;

    public function __construct(private LeaveReplacementService $service)
    {
    }

    public function index(Leave $leave, Request $request): JsonResponse
    {
        $filters = [
            'status' => $request->get('status'),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== null);

        return $this->success($this->service->listForLeave($leave, $filters), 'Leave replacements retrieved successfully');
    }

    public function store(LeaveReplacementRequest $request, Leave $leave): JsonResponse
    {
        $replacement = $this->service->create($leave, $request->validated(), (int) $request->user()->id);

        return $this->success(
            $replacement->load(['leave', 'originalEmployee', 'replacementEmployee', 'requester', 'approver']),
            'Leave replacement created successfully',
            201
        );
    }

    public function show(LeaveReplacement $replacement): JsonResponse
    {
        return $this->success(
            $replacement->load(['leave', 'originalEmployee', 'replacementEmployee', 'requester', 'approver']),
            'Leave replacement retrieved successfully'
        );
    }

    public function accept(LeaveReplacement $replacement): JsonResponse
    {
        $accepted = $this->service->accept($replacement);

        return $this->success($accepted, 'Leave replacement accepted successfully');
    }

    public function decline(Request $request, LeaveReplacement $replacement): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $declined = $this->service->decline($replacement, $validated['reason'] ?? null);

        return $this->success($declined, 'Leave replacement declined successfully');
    }

    public function destroy(LeaveReplacement $replacement): JsonResponse
    {
        $cancelled = $this->service->cancel($replacement);

        return $this->success($cancelled, 'Leave replacement cancelled successfully');
    }
}
