<?php

namespace App\Services;

use App\Models\ParameterRule;
use App\Models\ReadingSection;
use App\Models\ReadingSectionDefinition;
use App\Models\ReadingTemplateVersion;
use App\Models\ShiftTemplate;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OperationalConfigurationService
{
    public function __construct(private ReadingFrequencyService $frequencies) {}

    private const LEGACY_ROUND_OFFSETS = [30, 150, 270, 390, 510, 630];

    private const LEGACY_SECTIONS = [
        'process' => 'Processo',
        'flotators' => 'Flotadores',
        'decanter' => 'Decanter',
        'totalizers' => 'Totalizadores',
        'chemicals' => 'Produtos químicos',
        'observations' => 'Observações',
    ];

    /**
     * @return array{template: ?ShiftTemplate, operational_date: Carbon, starts_at: Carbon, ends_at: Carbon, round_offsets: array<int, int>}
     */
    public function shiftAt(CarbonInterface $moment): array
    {
        $now = Carbon::instance($moment)->copy()->timezone(config('app.timezone'));
        $template = $this->findTemplateFor($now);

        if (! $template) {
            return $this->legacyShiftAt($now);
        }

        $startsAt = substr($template->starts_at, 0, 8);
        $endsAt = substr($template->ends_at, 0, 8);
        $overnight = $startsAt >= $endsAt;
        $operationalDate = $overnight && $now->format('H:i:s') < $endsAt
            ? $now->copy()->subDay()->startOfDay()
            : $now->copy()->startOfDay();
        $start = $operationalDate->copy()->setTimeFromTimeString($startsAt);
        $end = $operationalDate->copy()->setTimeFromTimeString($endsAt);

        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        $offsets = $template->rounds
            ->where('is_active', true)
            ->sortBy('sort_order')
            ->pluck('offset_minutes')
            ->map(fn ($offset): int => (int) $offset)
            ->values()
            ->all();

        return [
            'template' => $template,
            'operational_date' => $operationalDate,
            'starts_at' => $start,
            'ends_at' => $end,
            'round_offsets' => $offsets ?: self::LEGACY_ROUND_OFFSETS,
        ];
    }

    /** @return Collection<int, array{id: ?int, key: string, label: string}> */
    public function sectionsAt(CarbonInterface $moment, ?ReadingTemplateVersion $templateVersion = null): Collection
    {
        if ($templateVersion) {
            return $templateVersion->sections()->where('is_active', true)->orderBy('sort_order')->get()->map(fn (ReadingSectionDefinition $definition): array => ['id' => $definition->id, 'key' => $definition->section_key, 'label' => $definition->label])->values();
        }
        if (Schema::hasTable('reading_section_definitions')) {
            $definitions = ReadingSectionDefinition::query()
                ->effectiveAt($moment)
                ->orderBy('section_key')
                ->orderByDesc('version')
                ->get()
                ->unique('section_key')
                ->sortBy('sort_order')
                ->values();

            if ($definitions->isNotEmpty()) {
                return $definitions->map(fn (ReadingSectionDefinition $definition): array => [
                    'id' => $definition->id,
                    'key' => $definition->section_key,
                    'label' => $definition->label,
                ]);
            }
        }

        return collect(self::LEGACY_SECTIONS)->map(
            fn (string $label, string $key): array => ['id' => null, 'key' => $key, 'label' => $label],
        )->values();
    }

    /** @return Collection<string, ParameterRule> */
    public function rulesForSection(ReadingSection $section): Collection
    {
        $section->loadMissing(['round', 'values.rule', 'definition.parameterRules.points']);
        $effectiveAt = $section->created_at ?? $section->round->created_at ?? $section->round->scheduled_at;

        if ($section->definition && $section->definition->reading_template_version_id) {
            $versionRules = $section->definition->parameterRules
                ->where('is_active', true)
                ->sortBy('sort_order')
                ->keyBy('field_key');

            foreach ($section->values as $value) {
                if ($value->rule) {
                    $versionRules->put($value->field_key, $value->rule);
                }
            }

            return $versionRules->filter(fn (ParameterRule $rule): bool => $this->frequencies->applies($rule, $section->round));
        }

        $effectiveRules = ParameterRule::query()
            ->where('section_key', $section->section_key)
            ->effectiveAt($effectiveAt)
            ->orderBy('field_key')
            ->orderByDesc('version')
            ->get()
            ->unique('field_key')
            ->keyBy('field_key');

        foreach ($section->values as $value) {
            if ($value->rule) {
                $effectiveRules->put($value->field_key, $value->rule);
            }
        }

        return $effectiveRules->sortBy('id')->filter(fn (ParameterRule $rule): bool => $this->frequencies->applies($rule, $section->round));
    }

    public function publishedReadingTemplate(): ?ReadingTemplateVersion
    {
        $query = ReadingTemplateVersion::query()
            ->where('status', ReadingTemplateVersion::Published)
            ->whereHas('template', fn (Builder $query) => $query->where('is_active', true))
            ->with('sections')
            ->orderByDesc('published_at');

        return (clone $query)
            ->whereHas('template', fn (Builder $query) => $query->where('is_default', true))
            ->first() ?? $query->first();
    }

    private function findTemplateFor(Carbon $moment): ?ShiftTemplate
    {
        if (! Schema::hasTable('shift_templates')) {
            return null;
        }

        $time = $moment->format('H:i:s');

        return ShiftTemplate::query()
            ->effectiveAt($moment)
            ->with('rounds')
            ->orderByDesc('version')
            ->get()
            ->first(function (ShiftTemplate $template) use ($time): bool {
                $startsAt = substr($template->starts_at, 0, 8);
                $endsAt = substr($template->ends_at, 0, 8);

                return $startsAt < $endsAt
                    ? $time >= $startsAt && $time < $endsAt
                    : $time >= $startsAt || $time < $endsAt;
            });
    }

    /**
     * @return array{template: null, operational_date: Carbon, starts_at: Carbon, ends_at: Carbon, round_offsets: array<int, int>}
     */
    private function legacyShiftAt(Carbon $now): array
    {
        $isDay = $now->hour >= 8 && $now->hour < 20;
        $operationalDate = (! $isDay && $now->hour < 8)
            ? $now->copy()->subDay()->startOfDay()
            : $now->copy()->startOfDay();
        $start = $operationalDate->copy()->setTime($isDay ? 8 : 20, 0);

        return [
            'template' => null,
            'operational_date' => $operationalDate,
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(12),
            'round_offsets' => self::LEGACY_ROUND_OFFSETS,
        ];
    }
}
