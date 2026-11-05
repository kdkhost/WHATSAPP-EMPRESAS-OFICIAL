<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EvolutionGoWebhookRequest;
use App\Services\EvolutionGoWebhookService;
use Illuminate\Http\JsonResponse;

class EvolutionGoWebhookController extends Controller
{
    public function __invoke(EvolutionGoWebhookRequest $request, EvolutionGoWebhookService $webhooks): JsonResponse
    {
        $result = $webhooks->receive($request);
        $status = (int) ($result['status'] ?? 202);
        unset($result['status']);

        return response()->json($result, $status);
    }
}
