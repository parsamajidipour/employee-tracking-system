<?php

namespace App\Services;

use App\Enums\CaseStatus;
use App\Enums\UserRole;
use App\Events\CaseAccessChanged;
use App\Events\CaseChanged;
use App\Models\CaseAssignmentHistory;
use App\Models\CaseStatusEvent;
use App\Models\InspectionCase;
use App\Models\User;
use App\Notifications\CaseAssignedNotification;
use App\Notifications\CaseStatusChangedNotification;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use LogicException;

final class CaseLifecycleService
{
    public function __construct(
        private readonly NotificationAudience $audience,
        private readonly CaseNotificationService $caseNotifications,
    ) {}

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

    /**
     * @param  Collection<int, User>  $employees
     */
    public function offer(InspectionCase $case, Collection $employees, User $actor): InspectionCase
    {
        if ($employees->isEmpty()) {
            throw new LogicException(__('messages.case.offer_required'));
        }

        if ($employees->contains(fn (User $employee) => $employee->role !== UserRole::Employee || ! $employee->is_active || $employee->trashed())) {
            throw new LogicException(__('messages.employee_not_assignable'));
        }

        [$updated, $removedEmployeeIds] = DB::transaction(function () use ($case, $employees, $actor) {
            $locked = InspectionCase::query()->lockForUpdate()->findOrFail($case->id);
            $this->guardOfferable($locked);
            $now = CarbonImmutable::now();
            $previousStatus = $locked->status;
            $employeeIds = $employees->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $previousEmployeeIds = $locked->offers()->pluck('employee_id')->map(fn ($id) => (int) $id)->all();
            $removedEmployeeIds = array_values(array_diff($previousEmployeeIds, $employeeIds));

            $locked->update([
                'assigned_to' => null,
                'assigned_at' => null,
                'status' => CaseStatus::Pending,
            ]);

            $locked->offers()->whereNotIn('employee_id', $employeeIds)->delete();
            foreach ($employeeIds as $employeeId) {
                $locked->offers()->updateOrCreate(
                    ['employee_id' => $employeeId],
                    ['offered_by' => $actor->id, 'offered_at' => $now],
                );
            }

            $names = $employees->pluck('name')->join(', ');
            $this->logEvent($locked, $actor, $previousStatus, CaseStatus::Pending, "Offered to {$names}.", $now);
            $this->caseNotifications->delete($locked->id, null, ['case.assigned']);

            return [$locked->fresh(), $removedEmployeeIds];
        });

        Notification::send($employees, new CaseAssignedNotification($updated));

        if ($removedEmployeeIds !== []) {
            event(new CaseAccessChanged($updated->id, $removedEmployeeIds));
        }

        event(CaseChanged::for('assigned', $updated));

        return $updated;
    }

    public function assign(InspectionCase $case, User $employee, User $actor): InspectionCase
    {
        return $this->offer($case, collect([$employee]), $actor);
    }

    public function withdrawOffers(User $employee): void
    {
        $caseIds = DB::transaction(function () use ($employee): array {
            $caseIds = $employee->caseOffers()->pluck('inspection_case_id')->map(fn ($id) => (int) $id)->all();
            $employee->caseOffers()->delete();

            foreach ($caseIds as $caseId) {
                $this->caseNotifications->delete($caseId, [$employee->id], ['case.assigned']);
            }

            return $caseIds;
        });

        foreach ($caseIds as $caseId) {
            event(new CaseAccessChanged($caseId, [$employee->id]));
            $case = InspectionCase::query()->find($caseId);
            if ($case !== null) {
                event(CaseChanged::for('offer-withdrawn', $case));
            }
        }
    }

    public function accept(InspectionCase $case, User $employee, CarbonInterface $plannedAt): InspectionCase
    {
        [$updated, $offeredEmployeeIds] = DB::transaction(function () use ($case, $employee, $plannedAt) {
            $locked = InspectionCase::query()->lockForUpdate()->findOrFail($case->id);
            $offeredEmployeeIds = $locked->offers()->pluck('employee_id')->map(fn ($id) => (int) $id)->all();
            $isLegacyAssignee = $locked->assigned_to === $employee->id;

            if ($locked->status !== CaseStatus::Pending || (! $isLegacyAssignee && ! in_array($employee->id, $offeredEmployeeIds, true))) {
                throw new LogicException(__('messages.case.offer_unavailable'));
            }

            $now = CarbonImmutable::now();
            $locked->update([
                'assigned_to' => $employee->id,
                'assigned_at' => $now,
                'status' => CaseStatus::Accepted,
                'accepted_at' => $now,
                'planned_at' => CarbonImmutable::instance($plannedAt),
            ]);
            $this->logEvent($locked, $employee, CaseStatus::Pending, CaseStatus::Accepted, 'Accepted by surveyor.', $now);
            CaseAssignmentHistory::create([
                'inspection_case_id' => $locked->id,
                'employee_id' => $employee->id,
                'actor_id' => $employee->id,
                'assigned_at' => $now,
            ]);
            $locked->offers()->delete();
            $this->caseNotifications->delete($locked->id, null, ['case.assigned']);

            return [$locked->fresh(), $offeredEmployeeIds];
        });

        Notification::send(
            $this->audience->managers(),
            new CaseStatusChangedNotification($updated, CaseStatus::Accepted, $employee->name),
        );

        if ($offeredEmployeeIds !== []) {
            event(new CaseAccessChanged($updated->id, $offeredEmployeeIds));
        }
        event(CaseChanged::for('accepted', $updated));

        return $updated;
    }

    public function reject(InspectionCase $case, User $employee, ?string $note): InspectionCase
    {
        [$updated, $legacyAssignment] = DB::transaction(function () use ($case, $employee, $note) {
            $locked = InspectionCase::query()->lockForUpdate()->findOrFail($case->id);
            $hasOffer = $locked->offers()->where('employee_id', $employee->id)->exists();
            $legacyAssignment = $locked->assigned_to === $employee->id;

            if ($locked->status !== CaseStatus::Pending || (! $hasOffer && ! $legacyAssignment)) {
                throw new LogicException(__('messages.case.offer_unavailable'));
            }

            $now = CarbonImmutable::now();
            if ($legacyAssignment) {
                $locked->update([
                    'assigned_to' => null,
                    'assigned_at' => null,
                    'status' => CaseStatus::Rejected,
                ]);
                $this->logEvent($locked, $employee, CaseStatus::Pending, CaseStatus::Rejected, $note ?? 'Rejected by surveyor.', $now);
            } else {
                $locked->offers()->where('employee_id', $employee->id)->delete();
                $this->logEvent($locked, $employee, CaseStatus::Pending, CaseStatus::Pending, $note ?? 'Offer declined by surveyor.', $now);
            }

            $this->caseNotifications->delete($locked->id, [$employee->id], ['case.assigned']);

            return [$locked->fresh(), $legacyAssignment];
        });

        if ($legacyAssignment) {
            Notification::send(
                $this->audience->managers(),
                new CaseStatusChangedNotification($updated, CaseStatus::Rejected, $employee->name, $note),
            );
        }

        event(new CaseAccessChanged($updated->id, [$employee->id]));
        event(CaseChanged::for('rejected', $updated));

        return $updated;
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
            throw new LogicException(__('messages.case.overdue_invalid'));
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
            throw new LogicException(__('messages.case.photo_required'));
        }

        $now = $this->transition($case, CaseStatus::Completed, $employee, $note ?? 'Inspection completed.');

        $case->update(['completed_at' => $now]);

        return $this->announce($case, CaseStatus::Completed, $employee, 'completed', $note);
    }

    public function cancel(InspectionCase $case, User $actor, ?string $note): InspectionCase
    {
        $assigneeId = $case->assigned_to;
        $offeredEmployeeIds = $case->offers()->pluck('employee_id')->map(fn ($id) => (int) $id)->all();
        $this->transition($case, CaseStatus::Cancelled, $actor, $note ?? 'Cancelled by management.');

        $case->offers()->delete();
        $this->caseNotifications->delete($case->id, $offeredEmployeeIds, ['case.assigned']);

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
        if ($offeredEmployeeIds !== []) {
            event(new CaseAccessChanged($fresh->id, $offeredEmployeeIds));
        }

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
            throw new LogicException(__('messages.case.not_assigned'));
        }
    }

    private function guardOfferable(InspectionCase $case): void
    {
        if (! in_array($case->status, [CaseStatus::Pending, CaseStatus::Rejected], true)) {
            throw new LogicException(__('messages.case.not_assignable'));
        }

        if ($case->status === CaseStatus::Pending && $case->assigned_to !== null) {
            throw new LogicException(__('messages.case.awaiting_response'));
        }
    }

    private function transition(InspectionCase $case, CaseStatus $to, User $actor, string $note): CarbonImmutable
    {
        if (! $case->status->canTransitionTo($to)) {
            throw new LogicException(__('messages.case.transition_invalid', [
                'from' => __('messages.case.status.'.$case->status->value),
                'to' => __('messages.case.status.'.$to->value),
            ]));
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
