<?php

namespace App\Services;

use App\Models\CasePhoto;
use App\Models\InspectionCase;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Expression;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class CasePhotoService
{
    /**
     * @param  array{lat: float, lng: float, accuracy_m: ?float, captured_at: string}  $gps
     */
    public function store(InspectionCase $case, User $employee, UploadedFile $file, array $gps): CasePhoto
    {
        $distance = (float) DB::selectOne(
            'SELECT ST_Distance(ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, location) AS meters FROM inspection_cases WHERE id = ?',
            [$gps['lng'], $gps['lat'], $case->id],
        )->meters;

        $path = $file->store("case-photos/{$case->id}", 'local');

        return CasePhoto::create([
            'inspection_case_id' => $case->id,
            'employee_id' => $employee->id,
            'disk_path' => $path,
            'location' => new Expression(sprintf(
                'ST_SetSRID(ST_MakePoint(%s, %s), 4326)::geography',
                sprintf('%.8F', $gps['lng']),
                sprintf('%.8F', $gps['lat']),
            )),
            'accuracy_m' => $gps['accuracy_m'] ?? null,
            'distance_from_case_m' => $distance,
            'is_gps_verified' => $distance <= (float) config('tracking.case_photo_radius_m'),
            'captured_at' => CarbonImmutable::parse($gps['captured_at']),
        ]);
    }

    public function delete(CasePhoto $photo): void
    {
        Storage::disk('local')->delete($photo->disk_path);
        $photo->delete();
    }
}
