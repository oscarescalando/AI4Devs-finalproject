<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Results\GetPublicLeaderboard;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class PublicLeaderboardController extends Controller
{
    /** GET /api/v1/public/events/{eventUlid}/leaderboard — pública, sin PII, cacheable. */
    public function __invoke(GetPublicLeaderboard $action, string $eventUlid): JsonResponse
    {
        return response()
            ->json($action($eventUlid))
            ->header('Cache-Control', 'public, max-age=3'); // sondeo corto del corte 1
    }
}
