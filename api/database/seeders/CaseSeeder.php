<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\InspectionCase;
use App\Models\User;
use App\Services\CaseLifecycleService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class CaseSeeder extends Seeder
{
    private const SYNTHETIC_ORIGIN_LAT = 23.55;

    private const SYNTHETIC_ORIGIN_LNG = 58.35;

    public function __construct(private readonly CaseLifecycleService $lifecycle) {}

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('CaseSeeder: skipped in production.');

            return;
        }

        if (InspectionCase::query()->exists()) {
            return;
        }

        $admin = User::where('role', UserRole::Admin)->first();
        $employees = User::employees()->active()->orderBy('id')->get();

        if ($admin === null || $employees->isEmpty()) {
            return;
        }

        $this->pending($admin, 1);
        $this->accepted($admin, $employees->get(0) ?? $employees->first(), 2);
        $this->inProgress($admin, $employees->get(1 % $employees->count()), 3);
        $this->completed($admin, $employees->get(2 % $employees->count()), 4);
        $this->rejected($admin, $employees->get(0), 5);
        $this->cancelled($admin, 6);
    }

    private function point(int $seed): array
    {
        return [
            'lat' => self::SYNTHETIC_ORIGIN_LAT + ($seed * 0.011),
            'lng' => self::SYNTHETIC_ORIGIN_LNG + ($seed * 0.013),
        ];
    }

    private function pending(User $admin, int $seed): void
    {
        $this->lifecycle->create([
            'reference_no' => 'INS-SEED-'.$seed,
            'title' => 'Villa valuation — unassigned',
            'property_address' => 'Seeb, Muscat',
            ...$this->point($seed),
            'priority' => 'normal',
        ], $admin);
    }

    private function accepted(User $admin, User $employee, int $seed): void
    {
        $case = $this->lifecycle->create([
            'reference_no' => 'INS-SEED-'.$seed,
            'title' => 'Bank inspection — Quriyat',
            'property_address' => 'Quriyat',
            ...$this->point($seed),
            'priority' => 'high',
        ], $admin);
        $case = $this->lifecycle->assign($case, $employee, $admin);
        $this->lifecycle->accept($case, $employee, CarbonImmutable::now()->addDay());
    }

    private function inProgress(User $admin, User $employee, int $seed): void
    {
        $case = $this->lifecycle->create([
            'reference_no' => 'INS-SEED-'.$seed,
            'title' => 'Apartment valuation — Al Mouj',
            'property_address' => 'Al Mouj, Muscat',
            ...$this->point($seed),
            'priority' => 'urgent',
        ], $admin);
        $case = $this->lifecycle->assign($case, $employee, $admin);
        $case = $this->lifecycle->accept($case, $employee, CarbonImmutable::now());
        $this->lifecycle->start($case, $employee);
    }

    private function completed(User $admin, User $employee, int $seed): void
    {
        $case = $this->lifecycle->create([
            'reference_no' => 'INS-SEED-'.$seed,
            'title' => 'Twin villa valuation — Bousher',
            'property_address' => 'Bousher, Muscat',
            ...$this->point($seed),
            'priority' => 'normal',
        ], $admin);
        $case = $this->lifecycle->assign($case, $employee, $admin);
        $case = $this->lifecycle->accept($case, $employee, CarbonImmutable::now()->subHours(3));
        $case = $this->lifecycle->start($case, $employee);
        $this->lifecycle->complete($case, $employee, 'Report submitted.');
    }

    private function rejected(User $admin, User $employee, int $seed): void
    {
        $case = $this->lifecycle->create([
            'reference_no' => 'INS-SEED-'.$seed,
            'title' => 'Commercial unit — Ruwi',
            'property_address' => 'Ruwi, Muscat',
            ...$this->point($seed),
            'priority' => 'normal',
        ], $admin);
        $case = $this->lifecycle->assign($case, $employee, $admin);
        $this->lifecycle->reject($case, $employee, 'Outside coverage area.');
    }

    private function cancelled(User $admin, int $seed): void
    {
        $case = $this->lifecycle->create([
            'reference_no' => 'INS-SEED-'.$seed,
            'title' => 'Land valuation — cancelled by customer',
            'property_address' => 'Barka',
            ...$this->point($seed),
            'priority' => 'normal',
        ], $admin);
        $this->lifecycle->cancel($case, $admin, 'Customer withdrew the request.');
    }
}
