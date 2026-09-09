<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Events\LeaveReplacementRequested;
use App\Events\LeaveReplacementStatusChanged;
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

        $replacement = LeaveReplacement::create([
            'leave_id' => $leave->id,
            'original_employee_id' => $leave->employee_id,
            'replacement_employee_id' => $data['replacement_employee_id'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'responsibilities' => $data['responsibilities'] ?? null,
            'status' => 'pending',
            'requested_by' => $requestedBy,
        ]);

        LeaveReplacementRequested::dispatch($replacement);

        return $replacement;
    }

    public function accept(LeaveReplacement $replacement): LeaveReplacement
    {
        $previousStatus = $replacement->status;
        $this->assertTransitionAllowed($replacement, 'accepted');

        if ($this->hasOtherAcceptedReplacement($replacement)) {
            throw new BusinessRuleException('An accepted replacement already exists for this leave');
        }

        $replacement->update([
            'status' => 'accepted',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Keep the legacy leave column in sync with the accepted replacement
        $replacement->leave->update(['replacement_employee_id' => $replacement->replacement_employee_id]);

        return $this->dispatchStatusChanged($replacement, $previousStatus);
    }

    public function decline(LeaveReplacement $replacement, ?string $reason = null): LeaveReplacement
    {
        $previousStatus = $replacement->status;
        $this->assertTransitionAllowed($replacement, 'declined');

        $replacement->update([
            'status' => 'declined',
            'rejection_reason' => $reason,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return $this->dispatchStatusChanged($replacement, $previousStatus);
    }

    public function cancel(LeaveReplacement $replacement): LeaveReplacement
    {
        $previousStatus = $replacement->status;
        $this->assertTransitionAllowed($replacement, 'cancelled');

        $replacement->update([
            'status' => 'cancelled',
        ]);

        // A cancelled accepted replacement releases the legacy leave column
        if ($previousStatus === 'accepted') {
            $replacement->leave->update(['replacement_employee_id' => null]);
        }

        return $this->dispatchStatusChanged($replacement, $previousStatus);
    }

    private function dispatchStatusChanged(LeaveReplacement $replacement, string $previousStatus): LeaveReplacement
    {
        $updated = $replacement->fresh();

        LeaveReplacementStatusChanged::dispatch($updated, $previousStatus);

        return $updated;
    }

    private function assertTransitionAllowed(LeaveReplacement $replacement, string $target): void
    {
        $allowed = self::TRANSITIONS[$replacement->status] ?? [];

        if (! in_array($target, $allowed, true)) {
            throw new BusinessRuleException("A {$replacement->status} replacement cannot be {$target}");
        }
    }

    private function hasOtherAcceptedReplacement(LeaveReplacement $replacement): bool
    {
        return LeaveReplacement::query()
            ->where('leave_id', $replacement->leave_id)
            ->where('status', 'accepted')
            ->whereKeyNot($replacement->getKey())
            ->exists();
    }
}
