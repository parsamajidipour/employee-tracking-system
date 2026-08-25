<?php

namespace App\Services;

use App\Enums\CaseStatus;
use App\Events\CaseChanged;
use App\Models\CaseStatusEvent;
use App\Models\InspectionCase;
use App\Models\User;
use App\Notifications\CaseAssignedNotification;
use App\Notifications\CaseStatusChangedNotification;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use LogicException;

final class CaseLifecycleService
{
    public function __construct(private readonly NotificationAudience $audience) {}

    /**
     * @param  array{reference_no: string, title: string, property_address: ?string, lat: float, lng: float, priority: string, notes: ?string}  $data
     */
    public function create(array $data, User $creator): InspectionCase
    {
        $case = DB::transaction(function () use ($data, $creator) {
            $case = InspectionCase::create([
                'reference_no' => $data['reference_no'],
                'title' => $data['title'],
                'property_address' => $data['property_address'] ?? null,
                'location' => $this->point($data['lat'], $data['lng']),
                'priority' => $data['priority'] ?? 'normal',
                'status' => CaseStatus::Pending,
                'created_by' => $creator->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $this->logEvent($case, $creator, null, CaseStatus::Pending, 'Case created.');

            return $case;
        });

        event(CaseChanged::for('created', $case));

        return $case;
    }

    public function assign(InspectionCase $case, User $employee, User $actor): InspectionCase
    {
        $this->guardAssignable($case, $employee);

        $updated = DB::transaction(function () use ($case, $employee, $actor) {
            $now = CarbonImmutable::now();
            $previousStatus = $case->status;
            $previousAssigneeId = $case->assigned_to;
            $previousAssigneeName = $previousAssigneeId
                ? User::query()->find($previousAssigneeId)?->name
                : null;

            $case->update([
                'assigned_to' => $employee->id,
                'assigned_at' => $now,
                'status' => CaseStatus::Pending,
            ]);

            $note = $previousAssigneeId
                ? "Reassigned from {$previousAssigneeName} to {$employee->name}."
                : "Assigned to {$employee->name}.";

            $from = $previousStatus === CaseStatus::Rejected
                ? CaseStatus::Rejected
                : ($previousAssigneeId ? CaseStatus::Pending : null);

            $this->logEvent($case, $actor, $from, CaseStatus::Pending, $note, $now);

            $employee->notify(new CaseAssignedNotification($case));

            return $case->fresh();
        });

        Notification::send(
            $this->audience->managers($actor->id),
            new CaseStatusChangedNotification($updated, CaseStatus::Pending, $actor->name, "Assigned to {$employee->name}."),
        );

        event(CaseChanged::for('assigned', $updated));

        return $updated;
    }

    public function accept(InspectionCase $case, User $employee, CarbonInterface $plannedAt): InspectionCase
    {
        $this->guardActor($case, $employee);
        $now = $this->transition($case, CaseStatus::Accepted, $employee, 'Accepted by surveyor.');

        $case->update(['accepted_at' => $now, 'planned_at' => CarbonImmutable::instance($plannedAt)]);

        return $this->announce($case, CaseStatus::Accepted, $employee, 'accepted', null);
    }

    public function reject(InspectionCase $case, User $employee, ?string $note): InspectionCase
    {
        $this->guardActor($case, $employee);
        $this->transition($case, CaseStatus::Rejected, $employee, $note ?? 'Rejected by surveyor.');

        return $this->announce($case, CaseStatus::Rejected, $employee, 'rejected', $note);
    }

    public function start(InspectionCase $case, User $employee): InspectionCase
    {
        $this->guardActor($case, $employee);
        $now = $this->transition($case, CaseStatus::InProgress, $employee, 'Inspection started.');

        $case->update(['started_at' => $now]);

        return $this->announce($case, CaseStatus::InProgress, $employee, 'started', null);
    }

    public function markOverdue(InspectionCase $case): InspectionCase
    {
        if ($case->status !== CaseStatus::Accepted || $case->planned_at === null || $case->planned_at->isFuture()) {
            throw new LogicException('Only a scheduled case past its planned time can become overdue.');
        }

        DB::transaction(function () use ($case) {
            $case->update(['status' => CaseStatus::Overdue]);
            $this->logEvent($case, null, CaseStatus::Accepted, CaseStatus::Overdue, 'Planned inspection time passed.');
        });

        $fresh = $case->fresh();
        $notification = new CaseStatusChangedNotification($fresh, CaseStatus::Overdue, null, 'Planned inspection time passed.');
        Notification::send($this->audience->managers(), $notification);
        $fresh->assignee?->notify($notification);
        event(CaseChanged::for('overdue', $fresh));

        return $fresh;
    }

    public function complete(InspectionCase $case, User $employee, ?string $note): InspectionCase
    {
        $this->guardActor($case, $employee);

        if (! $case->photos()->where('is_gps_verified', true)->exists()) {
            throw new LogicException('Add at least one GPS-verified site photo before completing the inspection.');
        }

        $now = $this->transition($case, CaseStatus::Completed, $employee, $note ?? 'Inspection completed.');

        $case->update(['completed_at' => $now]);

        return $this->announce($case, CaseStatus::Completed, $employee, 'completed', $note);
    }

    public function cancel(InspectionCase $case, User $actor, ?string $note): InspectionCase
    {
        $assigneeId = $case->assigned_to;
        $this->transition($case, CaseStatus::Cancelled, $actor, $note ?? 'Cancelled by management.');

        $fresh = $case->fresh();

        Notification::send(
            $this->audience->managers($actor->id),
            new CaseStatusChangedNotification($fresh, CaseStatus::Cancelled, $actor->name, $note),
        );

        if ($assigneeId !== null) {
            User::query()->find($assigneeId)?->notify(
                new CaseStatusChangedNotification($fresh, CaseStatus::Cancelled, $actor->name, $note),
            );
        }

        event(CaseChanged::for('cancelled', $fresh));

        return $fresh;
    }

    private function announce(InspectionCase $case, CaseStatus $to, User $actor, string $action, ?string $note): InspectionCase
    {
        $fresh = $case->fresh();

        Notification::send(
            $this->audience->managers(),
            new CaseStatusChangedNotification($fresh, $to, $actor->name, $note),
        );

        event(CaseChanged::for($action, $fresh));

        return $fresh;
    }

    private function guardActor(InspectionCase $case, User $employee): void
    {
        if ($case->assigned_to !== $employee->id) {
            throw new LogicException('This case is not assigned to this employee.');
        }
    }

    private function guardAssignable(InspectionCase $case, User $employee): void
    {
        if (! $employee->is_active) {
            throw new LogicException("{$employee->name} is deactivated and cannot be assigned a case.");
        }

        if (! in_array($case->status, [CaseStatus::Pending, CaseStatus::Rejected], true)) {
            throw new LogicException('Only a pending or rejected case can be assigned or reassigned.');
        }

        if ($case->status === CaseStatus::Pending && $case->assigned_to !== null) {
            throw new LogicException('This assignment is awaiting the surveyor response and cannot be replaced yet.');
        }
    }

    private function transition(InspectionCase $case, CaseStatus $to, User $actor, string $note): CarbonImmutable
    {
        if (! $case->status->canTransitionTo($to)) {
            throw new LogicException("Cannot move case from {$case->status->value} to {$to->value}.");
        }

        return DB::transaction(function () use ($case, $to, $actor, $note) {
            $now = CarbonImmutable::now();
            $from = $case->status;
            $case->update(['status' => $to]);
            $this->logEvent($case, $actor, $from, $to, $note, $now);

            return $now;
        });
    }

    private function logEvent(InspectionCase $case, ?User $actor, ?CaseStatus $from, CaseStatus $to, string $note, ?CarbonImmutable $createdAt = null): void
    {
        CaseStatusEvent::create([
            'inspection_case_id' => $case->id,
            'actor_id' => $actor?->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'created_at' => $createdAt ?? CarbonImmutable::now(),
        ]);
    }

    private function point(float $lat, float $lng): Expression
    {
        return new Expression(sprintf(
            'ST_SetSRID(ST_MakePoint(%s, %s), 4326)::geography',
            sprintf('%.8F', $lng),
            sprintf('%.8F', $lat),
        ));
    }
}
