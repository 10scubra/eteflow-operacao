<?php

namespace App\Http\Controllers;

use App\Services\IndicatorDataService;
use App\Services\PublicDashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicIndicatorController extends Controller
{
    public function show(Request $request, string $token, PublicDashboardService $shares, IndicatorDataService $data): View
    {
        $share = $shares->resolve($token);
        $page = $share->pages()->where('is_active', true)->where('is_shareable', true)->when($request->filled('page'), fn ($q) => $q->where('slug', $request->string('page')))->orderBy('sort_order')->firstOrFail();
        $timezone = $share->operationalUnit->timezone;
        $to = $request->filled('to') ? Carbon::parse($request->input('to'), $timezone)->endOfDay() : Carbon::now($timezone)->endOfDay();
        $from = $request->filled('from') ? Carbon::parse($request->input('from'), $timezone)->startOfDay() : $to->copy()->subDays(29)->startOfDay();
        $indicators = $page->indicators()->where('is_active', true)->where('is_shareable', true)->orderBy('sort_order')->get();
        $cards = $indicators->map(fn ($indicator) => $data->data($indicator, $from, $to, true));

        return view('indicators.public', compact('share', 'page', 'cards', 'from', 'to', 'token'));
    }
}
