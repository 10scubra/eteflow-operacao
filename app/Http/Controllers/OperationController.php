<?php

namespace App\Http\Controllers;

use App\Services\ChemicalStockService;
use App\Services\DailyOperationService;
use App\Services\OperationSnapshotService;
use App\Services\PermissionService;
use App\Support\ReadingDefinitionLabels;
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

        $shift->load('members.employee');

        return view('operation.readings', [
            'shift' => $shift,
            'timeline' => $timeline,
            'selectedRound' => $selected->load('sections'),
            'definitionLabels' => ReadingDefinitionLabels::all(),
        ]);
    }

    public function master(Request $request, DailyOperationService $operations): View
    {
        abort_unless($request->user()->role === 'master', 403);
        $operations->ensure();

        return view('operation.master');
    }

    public function snapshot(Request $request, OperationSnapshotService $snapshots, ChemicalStockService $stock, PermissionService $permissions): JsonResponse
    {
        abort_unless($request->user()->role === 'master', 403);

        $payload = $snapshots->build();
        if ($permissions->allows($request->user(), 'chemical_stock.view_balance')) {
            $rows = $stock->dashboard();
            $payload['chemical_stock'] = [
                'normal' => $rows->where('status', 'NORMAL')->count(),
                'low' => $rows->where('status', 'LOW')->count(),
                'critical' => $rows->where('status', 'CRITICAL')->count(),
                'empty' => $rows->where('status', 'EMPTY')->count(),
                'unknown' => $rows->where('status', 'UNKNOWN')->count(),
                'url' => route('chemical-stock.dashboard'),
            ];
        }

        return response()->json($payload);
    }
}
