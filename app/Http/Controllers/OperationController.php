<?php

namespace App\Http\Controllers;

use App\Services\DailyOperationService;
use App\Services\OperationSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationController extends Controller
{
    public function readings(Request $request, DailyOperationService $operations): View
    {
        abort_unless(in_array($request->user()->role, ['operator', 'master'], true), 403);
        $shift = $operations->ensure();
        $timeline = $operations->timeline($shift);
        $selected = $request->integer('round')
            ? $timeline['rounds']->firstWhere('id', $request->integer('round'))
            : $timeline['display'];

        abort_if($selected === null, 404);

        return view('operation.readings', [
            'shift' => $shift,
            'timeline' => $timeline,
            'selectedRound' => $selected->load('sections'),
        ]);
    }

    public function master(Request $request, DailyOperationService $operations): View
    {
        abort_unless($request->user()->role === 'master', 403);
        $operations->ensure();

        return view('operation.master');
    }

    public function snapshot(Request $request, OperationSnapshotService $snapshots): JsonResponse
    {
        abort_unless($request->user()->role === 'master', 403);

        return response()->json($snapshots->build());
    }
}
