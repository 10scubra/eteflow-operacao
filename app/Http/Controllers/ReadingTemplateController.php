<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreParameterRuleRequest;
use App\Http\Requests\StoreReadingSectionDefinitionRequest;
use App\Http\Requests\StoreReadingTemplateRequest;
use App\Http\Requests\StoreReadingTemplateVersionRequest;
use App\Models\Equipment;
use App\Models\ParameterRule;
use App\Models\ReadingSectionDefinition;
use App\Models\ReadingTemplate;
use App\Models\ReadingTemplateVersion;
use App\Services\ReadingDefinitionValidator;
use App\Services\ReadingTemplateService;
use App\Support\ReadingDefinitionLabels;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReadingTemplateController extends Controller
{
    public function index(): View
    {
        Gate::authorize('readings.configure');
        $templates = ReadingTemplate::query()->with(['versions' => fn ($query) => $query->orderByDesc('version')])->orderBy('name')->get();

        return view('admin.reading-templates.index', compact('templates'));
    }

    public function store(StoreReadingTemplateRequest $request, ReadingTemplateService $service): RedirectResponse
    {
        $template = ReadingTemplate::create($request->validated());
        $version = $service->createVersion($template, $request->user());

        return redirect()->route('admin.reading-templates.edit', $version)->with('success', 'Modelo criado em rascunho.');
    }

    public function createVersion(StoreReadingTemplateVersionRequest $request, ReadingTemplate $readingTemplate, ReadingTemplateService $service): RedirectResponse
    {
        $version = $service->createVersion($readingTemplate, $request->user(), $request->validated('notes'));

        return redirect()->route('admin.reading-templates.edit', $version);
    }

    public function edit(ReadingTemplateVersion $readingTemplateVersion): View
    {
        Gate::authorize('readings.configure');
        $readingTemplateVersion->load('template', 'sections.parameterRules.points');

        return view('admin.reading-templates.edit', ['version' => $readingTemplateVersion, 'dataTypes' => ReadingDefinitionValidator::DataTypes, 'frequencyTypes' => ReadingDefinitionValidator::FrequencyTypes, 'conditionOperators' => ReadingDefinitionValidator::ConditionOperators, 'definitionLabels' => ReadingDefinitionLabels::all(), 'equipment' => Equipment::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function storeSection(StoreReadingSectionDefinitionRequest $request, ReadingTemplateVersion $readingTemplateVersion, ReadingTemplateService $service): RedirectResponse
    {
        $service->assertEditable($readingTemplateVersion);
        $readingTemplateVersion->sections()->create([...$request->validated(), 'version' => $readingTemplateVersion->version, 'is_active' => $request->boolean('is_active', true)]);

        return back()->with('success', 'Seção adicionada.');
    }

    public function updateSection(StoreReadingSectionDefinitionRequest $request, ReadingSectionDefinition $readingSectionDefinition, ReadingTemplateService $service): RedirectResponse
    {
        $service->assertEditable($readingSectionDefinition->templateVersion);
        $readingSectionDefinition->update([...$request->validated(), 'is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Seção atualizada.');
    }

    public function storeParameter(StoreParameterRuleRequest $request, ReadingSectionDefinition $readingSectionDefinition, ReadingTemplateService $service, ReadingDefinitionValidator $validator): RedirectResponse
    {
        $service->assertEditable($readingSectionDefinition->templateVersion);
        $data = $request->safe()->except(['points', 'options_text', 'frequency_times_text', 'frequency_interval_hours', 'frequency_days']);
        $data['section_key'] = $readingSectionDefinition->section_key;
        $data['version'] = $readingSectionDefinition->version;
        $data['effective_from'] = now();
        $data['is_required'] = $request->boolean('is_required');
        $data['is_active'] = true;
        $data['frequency_config'] = $validator->validateFrequency($data['frequency_type'], $data['frequency_config'] ?? null);
        $this->validateVisibility($readingSectionDefinition, $data['field_key'], $data['visibility_config'] ?? null);

        DB::transaction(function () use ($readingSectionDefinition, $data, $request): void {
            $rule = $readingSectionDefinition->parameterRules()->create($data);
            foreach ($request->validated('points', []) as $point) {
                $rule->points()->create([...$point, 'is_active' => true]);
            }
        });

        return back()->with('success', 'Parâmetro adicionado.');
    }

    public function updateParameter(StoreParameterRuleRequest $request, ParameterRule $parameterRule, ReadingTemplateService $service, ReadingDefinitionValidator $validator): RedirectResponse
    {
        $parameterRule->loadMissing('sectionDefinition.templateVersion', 'points');
        abort_unless($parameterRule->sectionDefinition, 404);
        $service->assertEditable($parameterRule->sectionDefinition->templateVersion);
        $data = $request->safe()->except(['points', 'options_text', 'frequency_times_text', 'frequency_interval_hours', 'frequency_days']);
        $data['section_key'] = $parameterRule->sectionDefinition->section_key;
        $data['is_required'] = $request->boolean('is_required');
        $data['is_active'] = true;
        $data['frequency_config'] = $validator->validateFrequency($data['frequency_type'], $data['frequency_config'] ?? null);
        $this->validateVisibility($parameterRule->sectionDefinition, $data['field_key'], $data['visibility_config'] ?? null, $parameterRule);

        DB::transaction(function () use ($parameterRule, $data, $request): void {
            $parameterRule->update($data);
            $submittedKeys = collect($request->validated('points', []))->pluck('stable_key');
            $removed = $parameterRule->points()->whereNotIn('stable_key', $submittedKeys)->get();
            if ($removed->contains(fn ($point) => $point->values()->exists())) {
                throw ValidationException::withMessages(['points' => 'Um ponto ligado a leituras históricas não pode ser removido. Crie uma nova versão e mantenha-o inativo.']);
            }
            $parameterRule->points()->whereKey($removed->modelKeys())->delete();
            foreach ($request->validated('points', []) as $point) {
                $parameterRule->points()->updateOrCreate(
                    ['stable_key' => $point['stable_key']],
                    [...$point, 'is_active' => true],
                );
            }
        });

        return back()->with('success', 'Parâmetro atualizado.');
    }

    public function duplicate(ReadingTemplateVersion $readingTemplateVersion, ReadingTemplateService $service): RedirectResponse
    {
        Gate::authorize('readings.configure');
        $copy = $service->duplicate($readingTemplateVersion, request()->user());

        return redirect()->route('admin.reading-templates.edit', $copy)->with('success', 'Versão duplicada com definições independentes.');
    }

    public function publish(ReadingTemplateVersion $readingTemplateVersion, ReadingTemplateService $service): RedirectResponse
    {
        Gate::authorize('readings.configure');
        $service->publish($readingTemplateVersion, request()->user());

        return back()->with('success', 'Versão publicada e bloqueada para alterações estruturais.');
    }

    public function preview(ReadingTemplateVersion $readingTemplateVersion): View
    {
        Gate::authorize('readings.configure');
        $readingTemplateVersion->load('template', 'sections.parameterRules.points');

        return view('admin.reading-templates.preview', ['version' => $readingTemplateVersion, 'definitionLabels' => ReadingDefinitionLabels::all()]);
    }

    private function validateVisibility(ReadingSectionDefinition $section, string $fieldKey, ?array $visibility, ?ParameterRule $current = null): void
    {
        if (! $visibility) {
            return;
        }
        $controller = $section->parameterRules()
            ->where('field_key', $visibility['field_key'])
            ->where('is_active', true)
            ->first();
        if (! $controller || $controller->is($current) || $controller->field_key === $fieldKey) {
            throw ValidationException::withMessages(['visibility_config' => 'Selecione um parâmetro controlador válido da mesma seção.']);
        }
        if ($controller->data_type !== 'boolean') {
            throw ValidationException::withMessages(['visibility_config' => 'Nesta etapa, o parâmetro controlador deve ser do tipo Sim / Não.']);
        }
        $visited = [$fieldKey => true];
        while ($controller?->visibility_config) {
            $nextKey = $controller->visibility_config['field_key'] ?? null;
            if (! $nextKey || isset($visited[$nextKey])) {
                throw ValidationException::withMessages(['visibility_config' => 'A dependência configurada forma um ciclo e não pode ser salva.']);
            }
            $visited[$nextKey] = true;
            $controller = $section->parameterRules()->where('field_key', $nextKey)->first();
        }
    }
}
