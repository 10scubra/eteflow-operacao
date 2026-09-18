<?php

namespace App\Http\Controllers;

use App\Models\ChemicalProduct;
use App\Models\ChemicalProductPrice;
use App\Models\DashboardPage;
use App\Models\Indicator;
use App\Models\LaboratoryParameter;
use App\Models\ParameterRule;
use App\Models\PublicDashboardShare;
use App\Models\SamplingPoint;
use App\Services\AuditService;
use App\Services\PublicDashboardService;
use App\Services\UnitAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IndicatorAdminController extends Controller
{
    public function index(Request $request, UnitAccessService $units): View
    {
        Gate::authorize('indicators.configure');
        $unit = $units->resolve($request->user(), $request->integer('unit') ?: null);

        return view('admin.indicators.index', compact('unit') + ['units' => $units->accessible($request->user()), 'pages' => DashboardPage::where('operational_unit_id', $unit->id)->with('indicators')->orderBy('sort_order')->get(), 'parameters' => ParameterRule::where('is_active', true)->with(['points', 'sectionDefinition'])->orderBy('label')->get(), 'labParameters' => LaboratoryParameter::where('operational_unit_id', $unit->id)->orderBy('sort_order')->get(), 'points' => SamplingPoint::where('operational_unit_id', $unit->id)->orderBy('sort_order')->get(), 'shares' => PublicDashboardShare::where('operational_unit_id', $unit->id)->with('pages')->latest()->get(), 'products' => ChemicalProduct::where('is_active', true)->with('unit')->get()]);
    }

    public function parameter(Request $r, UnitAccessService $units, AuditService $audit): RedirectResponse
    {
        Gate::authorize('laboratory.configure');
        $unit = $units->resolve($r->user(), $r->integer('operational_unit_id'));
        $d = $r->validate(['stable_key' => ['required', 'alpha_dash', 'max:100'], 'name' => ['required', 'string', 'max:150'], 'default_unit' => ['required', 'string', 'max:30'], 'description' => ['nullable', 'string'], 'decimal_places' => ['required', 'integer', 'between:0,6'], 'sort_order' => ['required', 'integer', 'min:0']]);
        $p = LaboratoryParameter::create($d + ['operational_unit_id' => $unit->id, 'is_active' => true]);
        $audit->record($p, 'laboratory.parameter_created', $r->user(), [], $p->attributesToArray());

        return back()->with('success', 'Parâmetro laboratorial criado.');
    }

    public function point(Request $r, UnitAccessService $units, AuditService $audit): RedirectResponse
    {
        Gate::authorize('laboratory.configure');
        $unit = $units->resolve($r->user(), $r->integer('operational_unit_id'));
        $d = $r->validate(['stable_key' => ['required', 'alpha_dash', 'max:100'], 'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string'], 'sort_order' => ['required', 'integer', 'min:0']]);
        $p = SamplingPoint::create($d + ['operational_unit_id' => $unit->id, 'is_active' => true]);
        $audit->record($p, 'laboratory.point_created', $r->user(), [], $p->attributesToArray());

        return back()->with('success', 'Ponto de coleta criado.');
    }

    public function page(Request $r, UnitAccessService $units, AuditService $audit): RedirectResponse
    {
        Gate::authorize('indicators.configure');
        $unit = $units->resolve($r->user(), $r->integer('operational_unit_id'));
        $d = $r->validate(['stable_key' => ['required', 'alpha_dash', 'max:100'], 'name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string'], 'sort_order' => ['required', 'integer', 'min:0'], 'is_shareable' => ['nullable', 'boolean']]);
        $p = DashboardPage::create($d + ['operational_unit_id' => $unit->id, 'slug' => Str::slug($d['stable_key']), 'is_active' => true, 'is_shareable' => $r->boolean('is_shareable', true)]);
        $audit->record($p, 'indicator.page_created', $r->user(), [], $p->attributesToArray());

        return back()->with('success', 'Página criada.');
    }

    public function indicator(Request $r, UnitAccessService $units, AuditService $audit): RedirectResponse
    {
        Gate::authorize('indicators.configure');
        $unit = $units->resolve($r->user(), $r->integer('operational_unit_id'));
        $d = $r->validate(['dashboard_page_id' => ['required', 'integer'], 'stable_key' => ['required', 'alpha_dash', 'max:120'], 'name' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string'], 'source_type' => ['required', Rule::in(['OPERATIONAL_READING', 'LAB_ANALYSIS', 'EXTERNAL_ANALYSIS', 'DERIVED', 'ADMINISTRATIVE_COST'])], 'parameter_rule_id' => ['nullable', 'integer'], 'parameter_rule_point_id' => ['nullable', 'integer'], 'laboratory_parameter_id' => ['nullable', 'integer'], 'aggregation' => ['required', Rule::in(['RAW', 'LATEST', 'AVERAGE', 'MIN', 'MAX', 'SUM', 'COUNT', 'DELTA'])], 'visualization' => ['required', Rule::in(['LINE', 'BAR', 'AREA', 'KPI'])], 'unit' => ['nullable', 'string', 'max:30'], 'sort_order' => ['required', 'integer', 'min:0'], 'is_shareable' => ['nullable', 'boolean']]);
        abort_unless(DashboardPage::where('operational_unit_id', $unit->id)->whereKey($d['dashboard_page_id'])->exists(), 422);
        $i = Indicator::create($d + ['operational_unit_id' => $unit->id, 'is_active' => true, 'is_shareable' => $r->boolean('is_shareable', true)]);
        $audit->record($i, 'indicator.created', $r->user(), [], $i->attributesToArray());

        return back()->with('success', 'Indicador criado.');
    }

    public function share(Request $r, UnitAccessService $units, PublicDashboardService $service): RedirectResponse
    {
        Gate::authorize('public_shares.manage');
        $unit = $units->resolve($r->user(), $r->integer('operational_unit_id'));
        $d = $r->validate(['name' => ['required', 'string', 'max:180'], 'page_ids' => ['required', 'array', 'min:1'], 'page_ids.*' => ['integer'], 'expires_at' => ['nullable', 'date', 'after:now'], 'allow_export' => ['nullable', 'boolean']]);
        $created = $service->create($unit, $r->user(), $d);

        return back()->with('share_url', route('public.indicators.show', $created['token']))->with('success', 'Link criado. Copie agora; o token completo não será armazenado.');
    }

    public function revoke(Request $r, PublicDashboardShare $share, UnitAccessService $units, PublicDashboardService $service): RedirectResponse
    {
        Gate::authorize('public_shares.manage');
        abort_unless($units->canAccess($r->user(), $share->operational_unit_id), 403);
        $service->revoke($share, $r->user());

        return back()->with('success', 'Link revogado.');
    }

    public function price(Request $r, UnitAccessService $units, AuditService $audit): RedirectResponse
    {
        Gate::authorize('costs.manage');
        $unit = $units->resolve($r->user(), $r->integer('operational_unit_id'));
        $d = $r->validate(['chemical_product_id' => ['required', 'exists:chemical_products,id'], 'price' => ['required', 'numeric', 'gt:0'], 'unit_id' => ['required', 'exists:chemical_units,id'], 'effective_from' => ['required', 'date'], 'effective_until' => ['nullable', 'date', 'after:effective_from']]);
        $p = ChemicalProductPrice::create($d + ['operational_unit_id' => $unit->id, 'currency' => 'BRL', 'created_by' => $r->user()->id, 'configuration_snapshot' => $d]);
        $audit->record($p, 'cost.price_created', $r->user(), [], $p->attributesToArray());

        return back()->with('success', 'Preço com vigência registrado.');
    }
}
