<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceLoginRequest;
use App\Services\DeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceAuthController extends Controller
{
    private const STATUSES = [
        'invalid_credentials' => 401,
        'inactive' => 403,
        'device_conflict' => 409,
    ];

    public function __construct(private readonly DeviceService $devices) {}

    public function login(DeviceLoginRequest $request): JsonResponse
    {
        $result = $this->devices->login(
            $request->validated('identifier'),
            $request->validated('password'),
            $request->validated('device_identifier'),
            $request->validated('device_name'),
        );

        if (! $result->success) {
            return response()->json(
                ['message' => __('messages.device_login.'.$result->failureReason)],
                self::STATUSES[$result->failureReason],
            );
        }

        return response()->json(['token' => $result->token]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->devices->logout($request->user(), $request->user()->currentAccessToken()->id);

        return response()->json(status: 204);
    }
}
