<?php

namespace App\Http\Controllers\CourseHead;

use App\Http\Controllers\Controller;
use App\Services\NavigationBadgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConflictBadgeStatusController extends Controller
{
    public function __invoke(Request $request, NavigationBadgeService $badges): JsonResponse
    {
        return response()->json(
            $badges->courseHeadConflictBadgeStatusJson((int) $request->user()->id)
        );
    }
}
