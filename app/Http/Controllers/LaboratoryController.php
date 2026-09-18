<?php

namespace App\Http\Controllers;

use App\Models\LaboratoryCollection;
use App\Models\LaboratoryParameter;
use App\Models\LaboratoryResult;
use App\Models\SamplingPoint;
use App\Services\LaboratoryService;
use App\Services\UnitAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LaboratoryController extends Controller
{
    public function index(Request $request, UnitAccessService $units): View
    {
        Gate::authorize('laboratory.view');
        $unit = $units->resolve($request->user(), $request->integer('unit') ?: null);
        $collections = LaboratoryCollection::where('operational_unit_id', $unit->id)->with(['point', 'creator', 'results.parameter', 'results.corrections'])->latest('collected_at')->limit(100)->get();

        return view('laboratory.index', compact('unit', 'collections') + ['units' => $units->accessible($request->user())]);
    }

    public function create(Request $request, UnitAccessService $units): View
    {
        Gate::authorize('laboratory.create');
        $unit = $units->resolve($request->user(), $request->integer('unit') ?: null);

        return view('laboratory.create', compact('unit') + ['units' => $units->accessible($request->user()), 'parameters' => LaboratoryParameter::where('operational_unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->get(), 'points' => SamplingPoint::where('operational_unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->get()]);
    }

    public function store(Request $request, UnitAccessService $units, LaboratoryService $service): RedirectResponse
    {
        Gate::authorize('laboratory.create');
        $unit = $units->resolve($request->user(), $request->integer('operational_unit_id'));
        $data = $request->validate(['sampling_point_id' => ['required', 'integer'], 'origin_type' => ['required', 'in:INTERNAL,EXTERNAL'], 'collected_at' => ['required', 'date'], 'result_received_at' => ['nullable', 'date'], 'external_laboratory' => ['nullable', 'string', 'max:180'], 'document_reference' => ['nullable', 'string', 'max:255'], 'observation' => ['nullable', 'string', 'max:2000'], 'results' => ['required', 'array']]);
        $service->createCollection($unit, $request->user(), $data);

        return redirect()->route('laboratory.index', ['unit' => $unit->id])->with('success', 'Coleta e resultados registrados.');
    }

    public function correct(Request $request, LaboratoryResult $result, LaboratoryService $service, UnitAccessService $units): RedirectResponse
    {
        Gate::authorize('laboratory.correct');
        abort_unless($units->canAccess($request->user(), $result->collection()->value('operational_unit_id')), 403);
        $data = $request->validate(['corrected_value' => ['required', 'numeric'], 'reason' => ['required', 'string', 'min:5', 'max:2000']]);
        $service->correct($result, (float) $data['corrected_value'], $data['reason'], $request->user());

        return back()->with('success', 'Correção registrada sem apagar o valor original.');
    }
}
