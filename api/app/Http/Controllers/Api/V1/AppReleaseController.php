<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAppReleaseRequest;
use App\Http\Resources\AppReleaseResource;
use App\Models\AppRelease;
use App\Notifications\AppReleasePublishedNotification;
use App\Services\NotificationAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppReleaseController extends Controller
{
    private const LATEST_CACHE_KEY = 'app-release:latest';

    public function index(): AnonymousResourceCollection
    {
        return AppReleaseResource::collection(
            AppRelease::query()->orderByDesc('version_code')->get(),
        );
    }

    public function latest(): JsonResponse
    {
        $release = Cache::remember(
            self::LATEST_CACHE_KEY,
            now()->addDay(),
            fn () => AppRelease::query()->orderByDesc('version_code')->first(),
        );

        if ($release === null) {
            return response()->json(['message' => 'No release available.'], 404);
        }

        return AppReleaseResource::make($release)->response();
    }

    public function store(StoreAppReleaseRequest $request, NotificationAudience $audience): JsonResponse
    {
        $apk = $request->file('apk');
        $path = $apk->storeAs('releases', "app-v{$request->validated('version_code')}.apk", 'local');

        $release = AppRelease::create([
            'version_code' => $request->validated('version_code'),
            'version_name' => $request->validated('version_name'),
            'file_path' => $path,
            'file_size' => $apk->getSize(),
            'release_notes' => $request->validated('release_notes'),
            'is_mandatory' => $request->boolean('is_mandatory'),
        ]);

        Cache::forget(self::LATEST_CACHE_KEY);

        $employees = $audience->activeEmployees();

        if ($employees->isNotEmpty()) {
            Notification::send($employees, new AppReleasePublishedNotification($release));
        }

        return AppReleaseResource::make($release)->response()->setStatusCode(201);
    }

    public function destroy(AppRelease $appRelease): Response
    {
        Storage::disk('local')->delete($appRelease->file_path);
        $appRelease->delete();

        Cache::forget(self::LATEST_CACHE_KEY);

        return response()->noContent();
    }

    public function download(AppRelease $appRelease): BinaryFileResponse|JsonResponse
    {
        $path = Storage::disk('local')->path($appRelease->file_path);

        if (! is_readable($path)) {
            return response()->json(['message' => 'Release file is missing.'], 503);
        }

        return response()->file($path, [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Disposition' => "attachment; filename=\"smart-inspection-{$appRelease->version_name}.apk\"",
        ]);
    }
}
