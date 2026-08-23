<?php

namespace App\ValueObjects;

final class SurveyorCandidate
{
    public function __construct(
        public readonly int $employeeId,
        public readonly string $name,
        public readonly float $lat,
        public readonly float $lng,
        public readonly float $distanceM,
        public readonly int $openCaseCount,
        public readonly string $connectionStatus,
        public readonly string $recordedAt,
    ) {}

    /**
     * @return array{employee_id: int, name: string, lat: float, lng: float, distance_m: float, open_case_count: int, connection_status: string, recorded_at: string}
     */
    public function toArray(): array
    {
        return [
            'employee_id' => $this->employeeId,
            'name' => $this->name,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'distance_m' => round($this->distanceM, 1),
            'open_case_count' => $this->openCaseCount,
            'connection_status' => $this->connectionStatus,
            'recorded_at' => $this->recordedAt,
        ];
    }
}
