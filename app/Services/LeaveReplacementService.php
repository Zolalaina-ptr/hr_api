<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Leave;
use App\Models\LeaveReplacement;
use Illuminate\Database\Eloquent\Collection;

class LeaveReplacementService
{
    /**
     * Allowed status transitions.
     *
     * pending   -> accepted | declined | cancelled
     * accepted  -> cancelled
     * declined  -> (terminal)
     * cancelled -> (terminal)
     */
    private const TRANSITIONS = [
        'pending' => ['accepted', 'declined', 'cancelled'],
        'accepted' => ['cancelled'],
        'declined' => [],
        'cancelled' => [],
    ];

    public function listForLeave(Leave $leave, array $filters = []): Collection
    {
        return $leave->replacements()
            ->with(['originalEmployee', 'replacementEmployee', 'requester', 'approver'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->get();
    }

    public function create(Leave $leave, array $data, int $requestedBy): LeaveReplacement
    {
        if ($leave->replacements()->where('status', 'accepted')->exists()) {
            throw new BusinessRuleException('An accepted replacement already exists for this leave');
        }

        return LeaveReplacement::create([
            'leave_id' => $leave->id,
            'original_employee_id' => $leave->employee_id,
            'replacement_employee_id' => $data['replacement_employee_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'responsibilities' => $data['responsibilities'] ?? null,
            'status' => 'pending',
            'requested_by' => $requestedBy,
        ]);
    }

    public function accept(LeaveReplacement $replacement): LeaveReplacement
    {
        $this->assertTransitionAllowed($replacement, 'accepted');

        $replacement->update([
            'status' => 'accepted',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $replacement->fresh();
    }

    public function decline(LeaveReplacement $replacement, ?string $reason = null): LeaveReplacement
    {
        $this->assertTransitionAllowed($replacement, 'declined');

        $replacement->update([
            'status' => 'declined',
            'rejection_reason' => $reason,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $replacement->fresh();
    }

    public function cancel(LeaveReplacement $replacement): LeaveReplacement
    {
        $this->assertTransitionAllowed($replacement, 'cancelled');

        $replacement->update([
            'status' => 'cancelled',
        ]);

        return $replacement->fresh();
    }

    private function assertTransitionAllowed(LeaveReplacement $replacement, string $target): void
    {
        $allowed = self::TRANSITIONS[$replacement->status] ?? [];

        if (! in_array($target, $allowed, true)) {
            throw new BusinessRuleException("A {$replacement->status} replacement cannot be {$target}");
        }
    }
}
