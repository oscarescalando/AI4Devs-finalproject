<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Timing\ServerTime;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ServerTimeController extends Controller
{
    /** GET /api/v1/time — hora oficial del servidor (referencia del offset). */
    public function __invoke(ServerTime $action): JsonResponse
    {
        return response()->json($action());
    }
}
