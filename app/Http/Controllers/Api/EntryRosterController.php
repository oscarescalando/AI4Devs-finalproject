<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Registration\GetEventRoster;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class EntryRosterController extends Controller
{
    /** GET /api/v1/events/{eventUlid}/entries — roster para la estación (sin PII sensible). */
    public function index(GetEventRoster $action, string $eventUlid): JsonResponse
    {
        return response()->json($action($eventUlid));
    }
}
