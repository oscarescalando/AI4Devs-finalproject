<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Timing\RecordTimingMarkBatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\TimingMarkBatchRequest;
use Illuminate\Http\JsonResponse;

final class TimingMarkController extends Controller
{
    /** POST /api/v1/events/{eventUlid}/timing-marks — ISSUE-156. */
    public function store(TimingMarkBatchRequest $request, RecordTimingMarkBatch $action, string $eventUlid): JsonResponse
    {
        $results = $action($eventUlid, $request->validated('marks'));

        return response()->json(['results' => $results]);
    }
}
