<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Shipping\BoxNowService;
use App\Services\Shipping\OrderTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BoxNowWebhookController extends Controller
{
    public function __invoke(Request $request, BoxNowService $boxNow, OrderTrackingService $trackingService)
    {
        $signature = $request->header('x-boxnow-signature') ?: $request->input('datasignature');

        if (! $boxNow->verifyWebhookSignature($request->getContent(), $signature)) {
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        try {
            $result = $trackingService->applyBoxNowWebhook($request->all());

            return response()->json([
                'message' => $result['message'],
                'updated' => $result['updated'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Box Now webhook was received but not applied.', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
            ]);

            return response()->json(['message' => 'Webhook received.']);
        }
    }
}
