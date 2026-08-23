<?php

namespace App\Services;

use App\Enums\CaseStatus;
use App\Models\CaseStatusEvent;
use App\Models\InspectionCase;
use App\Models\User;
use App\Notifications\CaseAssignedNotification;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use LogicException;

final class CaseLifecycleService
{
    /**
     * @param  array{reference_no: string, title: string, property_address: ?string, lat: float, lng: float, priority: string, notes: ?string}  $data
     */
    public function create(array $data, User $creator): InspectionCase
    {
        return DB::transaction(function () use ($data, $creator) {
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
    }

    public function assign(InspectionCase $case, User $employee, User $actor): InspectionCase
    {
        return DB::transaction(function () use ($case, $employee, $actor) {
            $case->update([
                'assigned_to' => $employee->id,
                'assigned_at' => CarbonImmutable::now(),
                'status' => CaseStatus::Pending,
            ]);

            $this->logEvent($case, $actor, $case->status, CaseStatus::Pending, "Assigned to {$employee->name}.");

            $employee->notify(new CaseAssignedNotification($case));

            return $case->fresh();
        });
    }

    public function accept(InspectionCase $case, User $employee, CarbonInterface $plannedAt): InspectionCase
    {
        $this->guardActor($case, $employee);
        $this->transition($case, CaseStatus::Accepted, $employee, 'Accepted by surveyor.');

        $case->update(['accepted_at' => CarbonImmutable::now(), 'planned_at' => CarbonImmutable::instance($plannedAt)]);

        return $case->fresh();
    }

    public function reject(InspectionCase $case, User $employee, ?string $note): InspectionCase
    {
        $this->guardActor($case, $employee);
        $this->transition($case, CaseStatus::Rejected, $employee, $note ?? 'Rejected by surveyor.');

        return $case->fresh();
    }

    public function start(InspectionCase $case, User $employee): InspectionCase
    {
        $this->guardActor($case, $employee);
        $this->transition($case, CaseStatus::InProgress, $employee, 'Inspection started.');

        $case->update(['started_at' => CarbonImmutable::now()]);

        return $case->fresh();
    }

    public function complete(InspectionCase $case, User $employee, ?string $note): InspectionCase
    {
        $this->guardActor($case, $employee);
        $this->transition($case, CaseStatus::Completed, $employee, $note ?? 'Inspection completed.');

        $case->update(['completed_at' => CarbonImmutable::now()]);

        return $case->fresh();
    }

    public function cancel(InspectionCase $case, User $actor, ?string $note): InspectionCase
    {
        $this->transition($case, CaseStatus::Cancelled, $actor, $note ?? 'Cancelled by management.');

        return $case->fresh();
    }

    private function guardActor(InspectionCase $case, User $employee): void
    {
        if ($case->assigned_to !== $employee->id) {
            throw new LogicException('This case is not assigned to this employee.');
        }
    }

    private function transition(InspectionCase $case, CaseStatus $to, User $actor, string $note): void
    {
        if (! $case->status->canTransitionTo($to)) {
            throw new LogicException("Cannot move case from {$case->status->value} to {$to->value}.");
        }

        DB::transaction(function () use ($case, $to, $actor, $note) {
            $from = $case->status;
            $case->update(['status' => $to]);
            $this->logEvent($case, $actor, $from, $to, $note);
        });
    }

    private function logEvent(InspectionCase $case, ?User $actor, ?CaseStatus $from, CaseStatus $to, string $note): void
    {
        CaseStatusEvent::create([
            'inspection_case_id' => $case->id,
            'actor_id' => $actor?->id,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
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
