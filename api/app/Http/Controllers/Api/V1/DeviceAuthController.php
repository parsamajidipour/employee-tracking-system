<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceLoginRequest;
use App\Services\DeviceService;
use Illuminate\Http\JsonResponse;

class DeviceAuthController extends Controller
{
    private const STATUSES = [
        'invalid_credentials' => 401,
        'inactive' => 403,
        'device_conflict' => 409,
    ];

    private const MESSAGES = [
        'invalid_credentials' => 'These credentials do not match our records.',
        'inactive' => 'This account is not active.',
        'device_conflict' => 'A device is already registered for this account. Revoke it first.',
    ];

    public function __construct(private readonly DeviceService $devices) {}

    public function login(DeviceLoginRequest $request): JsonResponse
    {
        $result = $this->devices->login(
            $request->validated('username'),
            $request->validated('password'),
            $request->validated('device_identifier'),
            $request->validated('device_name'),
        );

        if (! $result->success) {
            return response()->json(
                ['message' => self::MESSAGES[$result->failureReason]],
                self::STATUSES[$result->failureReason],
            );
        }

        return response()->json(['token' => $result->token]);
    }
}
