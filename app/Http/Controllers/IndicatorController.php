<?php

namespace App\Http\Controllers;

use App\Models\DashboardPage;
use App\Services\IndicatorDataService;
use App\Services\UnitAccessService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class IndicatorController extends Controller
{
    public function index(Request $request, UnitAccessService $units, IndicatorDataService $data): View
    {
        Gate::authorize('indicators.view');
        $unit = $units->resolve($request->user(), $request->integer('unit') ?: null);
        $page = DashboardPage::where('operational_unit_id', $unit->id)->where('is_active', true)->when($request->filled('page'), fn ($q) => $q->where('slug', $request->string('page')))->with(['indicators' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])->orderBy('sort_order')->first();
        [$from,$to] = $this->period($request, $unit->timezone);
        $cards = $page ? $page->indicators->map(fn ($indicator) => $data->data($indicator, $from, $to)) : collect();

        return view('indicators.index', compact('unit', 'page', 'cards', 'from', 'to') + ['units' => $units->accessible($request->user()), 'pages' => DashboardPage::where('operational_unit_id', $unit->id)->where('is_active', true)->orderBy('sort_order')->get()]);
    }

    private function period(Request $request, string $timezone): array
    {
        $to = $request->filled('to') ? Carbon::parse($request->input('to'), $timezone)->endOfDay() : Carbon::now($timezone)->endOfDay();
        $from = $request->filled('from') ? Carbon::parse($request->input('from'), $timezone)->startOfDay() : $to->copy()->subDays(29)->startOfDay();
        abort_if($from->gt($to), 422, 'Período inválido.');

        return [$from, $to];
    }
}
