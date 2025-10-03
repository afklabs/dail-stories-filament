<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\FCMToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class FCMController extends Controller
{
    /**
     * Store or update FCM token
     */
    public function storeToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required|string|min:50',
            'device_id' => 'required|string|min:5',
            'platform' => 'required|in:android,ios',
            'device_info' => 'sometimes|array',
            'app_version' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $memberId = auth('sanctum')->id(); // null if guest

        $fcmToken = FCMToken::updateOrCreate(
            ['device_id' => $data['device_id']],
            [
                'fcm_token' => $data['fcm_token'],
                'member_id' => $memberId,
                'platform' => $data['platform'],
                'device_info' => $data['device_info'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'is_active' => true,
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'FCM token stored successfully',
            'data' => [
                'token_id' => $fcmToken->id,
                'device_id' => $fcmToken->device_id,
            ],
        ]);
    }

    /**
     * Delete FCM token (logout)
     */
    public function deleteToken(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        FCMToken::where('device_id', $request->device_id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'FCM token deleted successfully',
        ]);
    }

    /**
     * Get member's tokens
     */
    public function getTokens(): JsonResponse
    {
        $memberId = auth('sanctum')->id();

        if (!$memberId) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required',
            ], 401);
        }

        $tokens = FCMToken::forMember($memberId)
            ->active()
            ->select(['id', 'device_id', 'platform', 'app_version', 'last_used_at'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tokens,
        ]);
    }
}
