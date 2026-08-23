<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureCapability;
use App\Http\Requests\StoreCasePhotoRequest;
use App\Http\Resources\CasePhotoResource;
use App\Models\CasePhoto;
use App\Models\InspectionCase;
use App\Services\CasePhotoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CasePhotoController extends Controller
{
    public function store(StoreCasePhotoRequest $request, InspectionCase $case, CasePhotoService $photos): JsonResponse
    {
        abort_unless($case->assigned_to === $request->user()->id, 403);

        $photo = $photos->store($case, $request->user(), $request->file('photo'), [
            'lat' => (float) $request->validated('lat'),
            'lng' => (float) $request->validated('lng'),
            'accuracy_m' => $request->validated('accuracy_m'),
            'captured_at' => $request->validated('captured_at'),
        ]);

        return CasePhotoResource::make($photo)->response()->setStatusCode(201);
    }

    public function show(Request $request, CasePhoto $casePhoto): BinaryFileResponse|JsonResponse
    {
        $user = $request->user();
        $canView = $casePhoto->employee_id === $user->id || EnsureCapability::passes($user, Capability::ViewCases);
        abort_unless($canView, 403);

        $path = Storage::disk('local')->path($casePhoto->disk_path);
        if (! is_readable($path)) {
            return response()->json(['message' => 'Photo file is missing.'], 503);
        }

        return response()->file($path);
    }
}
